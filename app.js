/* global Papa, saveAs */

const SOURCE_LABEL = "Origine Catalogo 2026";
const LOCAL_SOURCE_URL = "origineCat2026.csv";
const LOCAL_SOURCE_NAME = `${SOURCE_LABEL} (locale)`;
const REMOTE_HTTPS_SOURCE_URL = "https://www.glasscom.it/Catalogo2026/origineCat2026.csv";
const REMOTE_HTTPS_SOURCE_NAME = `${SOURCE_LABEL} (remota HTTPS)`;
const REMOTE_HTTP_SOURCE_URL = "http://www.glasscom.it/Catalogo2026/origineCat2026.csv";
const REMOTE_HTTP_SOURCE_NAME = `${SOURCE_LABEL} (remota HTTP)`;
const SOURCE_CACHE_TEXT_KEY = "catalogo-source-cache-text";
const SOURCE_CACHE_META_KEY = "catalogo-source-cache-meta";

// Stato globale dell'applicazione
const state = {
  sourceRows: [],
  sourceMeta: null,
  modelRows: [],
  schema: [
    "Prodotto",
    "Categoria",
    "@image_01",
    "@image_scheda",
    "Nome Articolo",
    "Sottotitolo",
    "Codice Articolo",
    "cod1",
    "Prezzo_cod1",
    "fin1",
    "@image_fin1",
    "cod2",
    "Prezzo_cod2",
    "fin2",
    "@image_fin2",
    "cod3",
    "Prezzo_cod3",
    "fin3",
    "@image_fin3",
    "cod4",
    "Prezzo_cod4",
    "fin4",
    "@image_fin4",
    "cod5",
    "Prezzo_cod5",
    "fin5",
    "@image_fin5",
    "Dimensione",
    "Per Vetro",
    "Materiale",
    "UM",
    "@image_SchedeTecniche"
  ],
  columnMap: null,
  localStorageKey: "catalogo-model-rows"
};

/**
 * Format a timestamp into a localized string.
 * @param {number|undefined} timestamp
 * @returns {string}
 */
function formatTimestamp(timestamp) {
  if (!timestamp && timestamp !== 0) return "";
  const date = new Date(Number(timestamp));
  if (Number.isNaN(date.getTime())) return "";
  return date.toLocaleString("it-IT", {
    year: "numeric",
    month: "2-digit",
    day: "2-digit",
    hour: "2-digit",
    minute: "2-digit"
  });
}

/**
 * Mostra un messaggio temporaneo all'utente.
 * @param {string} text
 * @param {"success"|"error"|"warning"} type
 */
function showMessage(text, type = "success") {
  const messageBox = document.getElementById("message");
  if (!messageBox) return;
  messageBox.textContent = text;
  messageBox.className = "show " + type;
  setTimeout(() => {
    messageBox.className = messageBox.className.replace("show", "").trim();
  }, 4000);
}

/**
 * Salva la versione più recente dell'origine nel localStorage.
 * @param {string} text
 * @param {number} rowCount
 */
function persistSourceCache(text, meta = {}) {
  try {
    const storage = window.localStorage;
    if (!storage) return;
    storage.setItem(SOURCE_CACHE_TEXT_KEY, text);
    const payload = { ...meta };
    if (!payload.timestamp) {
      payload.timestamp = Date.now();
    }
    storage.setItem(SOURCE_CACHE_META_KEY, JSON.stringify(payload));
  } catch (error) {
    console.warn("Impossibile salvare la cache dell'origine", error);
  }
}

/**
 * Recupera l'ultima copia salvata dell'origine dal localStorage.
 * @returns {Promise<{rows: object[], columnMap: object, meta: {timestamp?: number}}|null>}
 */
async function getCachedSource() {
  try {
    const storage = window.localStorage;
    if (!storage) return null;
    const text = storage.getItem(SOURCE_CACHE_TEXT_KEY);
    if (!text) return null;
    const metaRaw = storage.getItem(SOURCE_CACHE_META_KEY);
    const meta = metaRaw ? JSON.parse(metaRaw) : {};
    const rows = await parseSourceCsv(text);
    if (!rows.length) return null;
    const columnMap = findColumns(rows[0]);
    if (!columnMap?.sku || !columnMap?.parent) return null;
    if (!meta.rowCount) {
      meta.rowCount = rows.length;
    }
    return {
      rows,
      columnMap,
      text,
      meta
    };
  } catch (error) {
    console.warn("Impossibile utilizzare la cache dell'origine", error);
    return null;
  }
}

/**
 * Applica una sorgente all'applicazione e aggiorna la UI.
 * @param {object[]} rows
 * @param {object} columnMap
 * @param {{name: string, rowCount: number, url?: string, cachedAt?: number, stale?: boolean}} meta
 */
function applySource(rows, columnMap, meta) {
  state.sourceRows = rows;
  state.columnMap = columnMap;
  state.sourceMeta = meta;
  updateFileInfo(meta);
}

/**
 * Cerca la prima colonna corrispondente tra i candidati.
 * Match case-insensitive, prima esatto poi per inclusione.
 * @param {string[]} columns
 * @param {string[]} candidates
 * @returns {string|undefined}
 */
function findColumn(columns, candidates) {
  if (!Array.isArray(columns) || !columns.length) return undefined;
  const lowered = columns.map((col) => col?.toString() ?? "");
  for (const candidate of candidates) {
    const target = candidate.toLowerCase();
    const index = lowered.findIndex((col) => col.toLowerCase() === target);
    if (index !== -1) {
      return columns[index];
    }
  }
  for (const candidate of candidates) {
    const target = candidate.toLowerCase();
    const index = lowered.findIndex((col) => col.toLowerCase().includes(target));
    if (index !== -1) {
      return columns[index];
    }
  }
  return undefined;
}

/**
 * Crea la mappa delle colonne utili analizzando la prima riga del CSV.
 * @param {object} row
 * @returns {object}
 */
function findColumns(row) {
  const columns = Object.keys(row || {});
  const map = {
    sku: findColumn(columns, ["sku"]),
    parent: findColumn(columns, ["parent_sku", "parent sku", "parent"]),
    title: findColumn(columns, ["post_title", "titolo"]),
    cat: findColumn(columns, ["categoria", "product_cat"]),
    price: findColumn(columns, ["regular_price", "price"]),
    fin: findColumn(columns, ["meta:attribute_pa_finitura", "finitura"]),
    um: findColumn(columns, ["um"]),
    mat: findColumn(columns, ["materiale"]),
    dim: findColumn(columns, ["dimensione", "size"]),
    glass: findColumn(columns, ["per vetro", "vetro"])
  };
  return map;
}

/**
 * Ritorna il valore della colonna indicata per una riga del CSV.
 * @param {object} row
 * @param {string} key
 * @returns {string}
 */
function getValue(row, key) {
  if (!row || !state.columnMap) return "";
  const column = state.columnMap[key];
  if (!column) return "";
  const value = row[column];
  if (value === undefined || value === null) return "";
  return String(value).trim();
}

/**
 * Stabilisce se il campo parent_sku della riga è vuoto.
 * @param {object} row
 * @returns {boolean}
 */
function isParentEmpty(row) {
  const parent = getValue(row, "parent");
  if (!parent) return true;
  const lowered = parent.toLowerCase();
  return ["", "nan", "none", "null", "na"].includes(lowered);
}

/**
 * Effettua il parsing del CSV di origine.
 * @param {File|string} data
 * @returns {Promise<object[]>}
 */
function parseSourceCsv(data) {
  return new Promise((resolve, reject) => {
    if (!window.Papa) {
      reject(new Error("Libreria Papa Parse non disponibile. Controlla la connessione e ricarica la pagina."));
      return;
    }
    Papa.parse(data, {
      delimiter: ";",
      header: true,
      skipEmptyLines: true,
      encoding: "UTF-8",
      complete: (results) => {
        if (results.errors && results.errors.length) {
          reject(new Error(results.errors[0].message));
          return;
        }
        const rows = (results.data || []).filter((row) => {
          if (!row || typeof row !== "object") return false;
          return Object.values(row).some((value) => {
            if (value === undefined || value === null) return false;
            return String(value).trim() !== "";
          });
        });
        if (!rows.length) {
          reject(new Error("File CSV vuoto o senza dati validi."));
          return;
        }
        resolve(rows);
      },
      error: (error) => reject(error)
    });
  });
}

/**
 * Trova l'indice della riga main a partire da uno SKU (padre o variante).
 * @param {string} code
 * @returns {number}
 */
function indexOfMainForCode(code) {
  if (!code) return -1;
  const rows = state.sourceRows;
  for (let i = 0; i < rows.length; i += 1) {
    const sku = getValue(rows[i], "sku");
    if (sku.toLowerCase() === code.toLowerCase()) {
      if (isParentEmpty(rows[i])) {
        return i;
      }
      for (let j = i - 1; j >= 0; j -= 1) {
        if (isParentEmpty(rows[j])) {
          return j;
        }
      }
      return -1;
    }
  }
  return -1;
}

/**
 * Colleziona il gruppo main + varianti a partire da un indice.
 * @param {number} startIndex
 * @returns {{main: object, variants: object[]}}
 */
function collectGroup(startIndex) {
  const rows = state.sourceRows;
  const main = rows[startIndex];
  const variants = [];
  for (let i = startIndex + 1; i < rows.length; i += 1) {
    if (isParentEmpty(rows[i])) {
      break;
    }
    variants.push(rows[i]);
  }
  return { main, variants };
}

/**
 * Normalizza un prezzo in formato standard con due decimali.
 * @param {string} value
 * @returns {string}
 */
function normalizePrice(value) {
  if (!value) return "";
  const raw = String(value).trim();
  if (!raw) return "";
  let normalized = raw.replace(/\s+/g, "");
  normalized = normalized.replace(/[^0-9,.-]/g, "");
  if (/[,]/.test(normalized) && /[.]/.test(normalized)) {
    normalized = normalized.replace(/[.]/g, "").replace(/,/g, ".");
  } else if (/,/.test(normalized)) {
    normalized = normalized.replace(/,/g, ".");
  }
  const number = Number.parseFloat(normalized);
  if (Number.isNaN(number)) return "";
  return number.toFixed(2);
}

/**
 * Costruisce una riga del modello finale.
 * @param {object} main
 * @param {object[]} variants
 * @returns {object}
 */
function buildModelRow(main, variants) {
  const row = {};
  state.schema.forEach((key) => {
    row[key] = "";
  });

  const codiceArticolo = getValue(main, "sku");
  row["Prodotto"] = "";
  row["Categoria"] = getValue(main, "cat");
  row["@image_01"] = codiceArticolo ? `singoli-componenti/images/${codiceArticolo}.png` : "";
  row["@image_scheda"] = codiceArticolo ? `singoli-componenti/pdf/${codiceArticolo}.pdf` : "";
  row["Nome Articolo"] = getValue(main, "title");
  row["Sottotitolo"] = "";
  row["Codice Articolo"] = codiceArticolo;

  const limitedVariants = variants.slice(0, 5);
  if (variants.length > 5) {
    showMessage("Varianti oltre 5 ignorate", "warning");
  }

  limitedVariants.forEach((variant, index) => {
    const slot = index + 1;
    const codKey = `cod${slot}`;
    const priceKey = `Prezzo_cod${slot}`;
    const finKey = `fin${slot}`;
    const imageKey = `@image_fin${slot}`;
    const variantSku = getValue(variant, "sku");
    row[codKey] = variantSku;
    row[priceKey] = normalizePrice(getValue(variant, "price"));
    row[finKey] = getValue(variant, "fin");
    row[imageKey] = variantSku ? `singoli-componenti/images/${variantSku}.png` : "";
  });

  row["Dimensione"] = getValue(main, "dim");
  row["Per Vetro"] = getValue(main, "glass");
  row["Materiale"] = getValue(main, "mat");
  row["UM"] = getValue(main, "um");
  row["@image_SchedeTecniche"] = "";

  return row;
}

/**
 * Aggiunge una riga all'anteprima cercando per codice.
 * @param {string} code
 */
function addByCode(code) {
  const trimmed = code.trim();
  if (!trimmed) {
    showMessage("Inserisci un codice valido", "error");
    return;
  }
  if (!state.sourceRows.length || !state.columnMap) {
    showMessage("Origine non disponibile. Attendi il caricamento e riprova.", "error");
    return;
  }
  const index = indexOfMainForCode(trimmed);
  if (index === -1) {
    showMessage(`Codice ${trimmed} non trovato`, "warning");
    return;
  }
  const { main, variants } = collectGroup(index);
  const codiceArticolo = getValue(main, "sku");
  if (!codiceArticolo) {
    showMessage("Il codice padre è vuoto nella sorgente", "error");
    return;
  }
  const alreadyExists = state.modelRows.some((row) => row["Codice Articolo"].toLowerCase() === codiceArticolo.toLowerCase());
  if (alreadyExists) {
    showMessage(`Codice ${codiceArticolo} già presente nell'anteprima`, "warning");
    return;
  }
  const modelRow = buildModelRow(main, variants);
  state.modelRows.push(modelRow);
  persistModelRows();
  renderTable();
  showMessage(`Aggiunto gruppo ${codiceArticolo}`, "success");
}

/**
 * Rimuove una riga dal modello finale.
 * @param {number} index
 */
function removeRow(index) {
  state.modelRows.splice(index, 1);
  persistModelRows();
  renderTable();
  showMessage("Riga rimossa", "success");
}

/**
 * Svuota completamente l'anteprima.
 */
function clearAll() {
  if (!state.modelRows.length) return;
  state.modelRows = [];
  persistModelRows();
  renderTable();
  showMessage("Anteprima svuotata", "success");
}

/**
 * Esporta il CSV finale utilizzando FileSaver.
 */
function exportCsv() {
  if (!state.modelRows.length) {
    showMessage("Anteprima vuota, niente da esportare", "warning");
    return;
  }
  const rows = [state.schema.join(";")];
  state.modelRows.forEach((row) => {
    const line = state.schema
      .map((key) => {
        const value = row[key] ?? "";
        const sanitized = String(value).replace(/"/g, '""');
        if (sanitized.includes(";") || sanitized.includes('"') || sanitized.includes("\n")) {
          return `"${sanitized}"`;
        }
        return sanitized;
      })
      .join(";");
    rows.push(line);
  });
  const blob = new Blob([rows.join("\n")], { type: "text/csv;charset=utf-8" });
  saveAs(blob, "modello_finale.csv");
  showMessage("CSV esportato", "success");
}

/**
 * Scarica un CSV vuoto con solo l'header del modello.
 */
function downloadEmptyTemplate() {
  const header = state.schema.join(";");
  const blob = new Blob([`${header}\n`], { type: "text/csv;charset=utf-8" });
  saveAs(blob, "modello_vuoto.csv");
  showMessage("Modello vuoto scaricato", "success");
}

/**
 * Renderizza la tabella dell'anteprima e aggiorna i contatori.
 */
function renderTable() {
  const headerRow = document.getElementById("preview-header");
  const body = document.getElementById("preview-body");
  const count = document.getElementById("preview-count");

  if (headerRow.childElementCount === 0) {
    state.schema.forEach((key) => {
      const th = document.createElement("th");
      th.textContent = key;
      headerRow.appendChild(th);
    });
    const thActions = document.createElement("th");
    thActions.textContent = "Azioni";
    headerRow.appendChild(thActions);
  }

  body.innerHTML = "";
  state.modelRows.forEach((row, index) => {
    const tr = document.createElement("tr");
    state.schema.forEach((key) => {
      const td = document.createElement("td");
      td.textContent = row[key] ?? "";
      tr.appendChild(td);
    });
    const tdActions = document.createElement("td");
    const button = document.createElement("button");
    button.type = "button";
    button.className = "danger remove-btn";
    button.textContent = "Rimuovi";
    button.setAttribute("aria-label", `Rimuovi riga ${row["Codice Articolo"] || index + 1}`);
    button.addEventListener("click", () => removeRow(index));
    tdActions.appendChild(button);
    tr.appendChild(tdActions);
    body.appendChild(tr);
  });

  count.textContent = `${state.modelRows.length} righe`;
}

/**
 * Aggiorna le informazioni dell'origine caricata.
 * @param {{name: string, rowCount: number, url?: string, cachedAt?: number, stale?: boolean}|null} meta
 */
function updateFileInfo(meta) {
  const nameEl = document.getElementById("file-name");
  const countEl = document.getElementById("row-count");
  const metaEl = document.getElementById("file-meta");
  if (!nameEl || !countEl || !metaEl) return;
  if (meta) {
    const baseName = meta.name || SOURCE_LABEL;
    nameEl.textContent = baseName + (meta.url ? ` (${meta.url})` : "");
    if (meta.url) {
      nameEl.setAttribute("title", meta.url);
    } else {
      nameEl.removeAttribute("title");
    }
    countEl.textContent = `${meta.rowCount} righe`;
    const details = [];
    switch (meta.sourceType) {
      case "local":
        details.push("Fonte locale");
        break;
      case "remote-https":
        details.push("Fonte remota (HTTPS)");
        break;
      case "remote-http":
        details.push("Fonte remota (HTTP)");
        break;
      case "cache":
        details.push("Fonte ripristinata dalla cache");
        break;
      default:
        if (meta.sourceType) {
          details.push(meta.sourceType);
        }
        break;
    }
    if (meta.cachedAt) {
      details.push(`Ultimo aggiornamento: ${formatTimestamp(meta.cachedAt)}`);
    }
    if (meta.stale) {
      details.push("Mostrando copia salvata");
    }
    metaEl.textContent = details.join(" • ");
    metaEl.hidden = details.length === 0;
  } else {
    nameEl.textContent = "Origine non caricata";
    countEl.textContent = "0 righe";
    metaEl.textContent = "";
    metaEl.hidden = true;
    nameEl.removeAttribute("title");
  }
}

/**
 * Salva le righe del modello su localStorage.
 */
function persistModelRows() {
  try {
    window.localStorage.setItem(state.localStorageKey, JSON.stringify(state.modelRows));
  } catch (error) {
    console.warn("Impossibile salvare su localStorage", error);
  }
}

/**
 * Ripristina le righe del modello da localStorage.
 */
function restoreModelRows() {
  try {
    const raw = window.localStorage.getItem(state.localStorageKey);
    if (!raw) return;
    const rows = JSON.parse(raw);
    if (Array.isArray(rows)) {
      state.modelRows = rows;
      renderTable();
    }
  } catch (error) {
    console.warn("Impossibile ripristinare da localStorage", error);
  }
}

/**
 * Scarica il contenuto di un CSV da un URL specifico.
 * @param {string} url
 * @param {RequestInit} options
 * @returns {Promise<string>}
 */
async function fetchSourceText(url, options = {}) {
  const response = await fetch(url, options);
  if (!response.ok) {
    throw new Error(`Richiesta fallita (${response.status})`);
  }
  return response.text();
}

/**
 * Produce un messaggio leggibile per gli errori durante il caricamento dell'origine.
 * @param {{sourceType: string}} attempt
 * @param {Error} error
 * @param {{isFileProtocol?: boolean, isHttpsPage?: boolean}} context
 * @returns {string}
 */
function describeSourceError(attempt, error, context = {}) {
  const baseMessage = (error && error.message) || String(error) || "Errore sconosciuto";
  const hints = [];
  const normalizedBase = baseMessage.trim();
  const failedToFetch = /failed to fetch/i.test(normalizedBase);

  if (attempt.sourceType === "local") {
    if (context.isFileProtocol && failedToFetch) {
      hints.push(
        "Accesso al file locale bloccato dal browser (protocollo file://). Avvia un server statico nella cartella del progetto."
      );
    }
    if (/\(404\)/.test(normalizedBase)) {
      hints.push("File locale non trovato accanto alla pagina.");
    }
  }

  if (attempt.sourceType === "remote-http" && context.isHttpsPage && failedToFetch) {
    hints.push("Richiesta HTTP bloccata perché la pagina è servita tramite HTTPS (contenuto misto).");
  }

  if (attempt.sourceType?.startsWith("remote") && failedToFetch && hints.length === 0) {
    hints.push("Download remoto non riuscito (controlla la connessione o i permessi CORS della sorgente).");
  }

  if (!hints.length) {
    return normalizedBase;
  }

  const uniqueHints = Array.from(new Set(hints));
  return `${uniqueHints.join(" ")} (Dettagli: ${normalizedBase}).`;
}

/**
 * Carica la sorgente preferendo il file locale e, in alternativa, la copia remota.
 * Salva sempre l'ultima versione funzionante nella cache locale.
 * @param {boolean} showNotification
 */
async function loadSource(showNotification = true) {
  const nameEl = document.getElementById("file-name");
  const countEl = document.getElementById("row-count");
  const reloadBtn = document.getElementById("reload-btn");
  const { protocol, href } = window.location;
  const isFileProtocol = protocol === "file:";
  const isHttpsPage = protocol === "https:";
  if (nameEl) {
    nameEl.textContent = "Caricamento origine...";
  }
  if (countEl) {
    countEl.textContent = "--";
  }
  if (reloadBtn) {
    reloadBtn.disabled = true;
  }
  try {
    const attempts = [];
    try {
      const absoluteLocalUrl = new URL(LOCAL_SOURCE_URL, href).href;
      attempts.push({
        url: absoluteLocalUrl,
        displayUrl: LOCAL_SOURCE_URL,
        name: LOCAL_SOURCE_NAME,
        sourceType: "local",
        fetchOptions: {
          cache: "no-store"
        }
      });
    } catch (error) {
      console.warn("Impossibile costruire l'URL assoluto per la sorgente locale", error);
    }

    attempts.push({
      url: REMOTE_HTTPS_SOURCE_URL,
      displayUrl: REMOTE_HTTPS_SOURCE_URL,
      name: REMOTE_HTTPS_SOURCE_NAME,
      sourceType: "remote-https",
      fetchOptions: {
        cache: "no-cache",
        mode: "cors",
        credentials: "omit"
      }
    });

    if (!isHttpsPage) {
      attempts.push({
        url: REMOTE_HTTP_SOURCE_URL,
        displayUrl: REMOTE_HTTP_SOURCE_URL,
        name: REMOTE_HTTP_SOURCE_NAME,
        sourceType: "remote-http",
        fetchOptions: {
          cache: "no-cache",
          mode: "cors",
          credentials: "omit"
        }
      });
    }

    const errors = [];
    for (const attempt of attempts) {
      try {
        const text = await fetchSourceText(attempt.url, attempt.fetchOptions);
        const rows = await parseSourceCsv(text);
        const columnMap = findColumns(rows[0]);
        if (!columnMap?.sku || !columnMap?.parent) {
          throw new Error("Colonne essenziali mancanti nella sorgente.");
        }
        const timestamp = Date.now();
        persistSourceCache(text, {
          rowCount: rows.length,
          name: attempt.name,
          url: attempt.displayUrl ?? attempt.url,
          sourceType: attempt.sourceType,
          timestamp
        });
        applySource(rows, columnMap, {
          name: attempt.name,
          rowCount: rows.length,
          url: attempt.displayUrl ?? attempt.url,
          cachedAt: timestamp,
          stale: false,
          sourceType: attempt.sourceType
        });
        if (showNotification) {
          const successLabel =
            attempt.sourceType === "local"
              ? "Origine locale caricata"
              : attempt.sourceType === "remote-http"
                ? "Origine remota HTTP caricata"
                : "Origine remota HTTPS caricata";
          showMessage(`${successLabel} (${rows.length} righe)`, "success");
        }
        return true;
      } catch (error) {
        const friendlyMessage = describeSourceError(attempt, error, { isFileProtocol, isHttpsPage });
        console.warn(`Impossibile caricare ${attempt.name} (${attempt.url})`, error);
        errors.push({ attempt, error, message: friendlyMessage });
      }
    }

    const cached = await getCachedSource();
    if (cached) {
      applySource(cached.rows, cached.columnMap, {
        name: cached.meta?.name ?? SOURCE_LABEL,
        rowCount: cached.rows.length,
        url: cached.meta?.url,
        cachedAt: cached.meta?.timestamp,
        stale: true,
        sourceType: cached.meta?.sourceType ?? "cache"
      });
      const details = errors
        .map(({ attempt, message }) => {
          const label =
            attempt.sourceType === "local"
              ? "locale"
              : attempt.sourceType === "remote-http"
                ? "remota HTTP"
                : "remota HTTPS";
          return `${label}: ${message}`;
        })
        .join(" | ");
      const fallbackMessage = details
        ? `Impossibile aggiornare l'origine (${details}). È stata utilizzata l'ultima copia salvata.`
        : "Impossibile aggiornare l'origine. È stata utilizzata l'ultima copia salvata.";
      showMessage(fallbackMessage, "warning");
      return false;
    }

    state.sourceRows = [];
    state.columnMap = null;
    state.sourceMeta = null;
    updateFileInfo(null);
    const details = errors
      .map(({ attempt, message }) => {
        const label =
          attempt.sourceType === "local"
            ? "locale"
            : attempt.sourceType === "remote-http"
              ? "remota HTTP"
              : "remota HTTPS";
        return `${label}: ${message}`;
      })
      .join(" | ");
    const message = details
      ? `Origine non disponibile: ${details}. Verifica di aver posizionato il file locale o la connessione alla sorgente remota.`
      : "Origine non disponibile. Controlla la presenza del file locale o la connessione.";
    const hint =
      isFileProtocol && errors.some(({ attempt }) => attempt.sourceType === "local")
        ? " Nota: i browser bloccano l'accesso diretto ai file locali. Avvia un piccolo server statico o utilizza l'origine remota."
        : "";
    showMessage(message + hint, "error");
    return false;
  } catch (error) {
    console.error(error);
    showMessage(error.message || "Errore imprevisto durante il caricamento dell'origine", "error");
    return false;
  } finally {
    if (reloadBtn) {
      reloadBtn.disabled = false;
    }
  }
}

/**
 * Inizializza gli event listener dell'interfaccia.
 */
function setupListeners() {
  const reloadBtn = document.getElementById("reload-btn");
  const addBtn = document.getElementById("add-btn");
  const skuInput = document.getElementById("sku-input");
  const exportBtn = document.getElementById("export-btn");
  const clearBtn = document.getElementById("clear-btn");
  const downloadTemplateBtn = document.getElementById("download-template");

  reloadBtn.addEventListener("click", () => {
    loadSource();
  });

  addBtn.addEventListener("click", () => {
    addByCode(skuInput.value);
    skuInput.focus();
    skuInput.select();
  });

  skuInput.addEventListener("keydown", (event) => {
    if (event.key === "Enter") {
      event.preventDefault();
      addByCode(skuInput.value);
    }
  });

  exportBtn.addEventListener("click", exportCsv);
  clearBtn.addEventListener("click", clearAll);
  downloadTemplateBtn.addEventListener("click", downloadEmptyTemplate);
}

// Bootstrap dell'applicazione
window.addEventListener("DOMContentLoaded", async () => {
  updateFileInfo(null);
  setupListeners();
  restoreModelRows();
  renderTable();
  const cached = await getCachedSource();
  if (cached) {
    applySource(cached.rows, cached.columnMap, {
      name: cached.meta?.name ?? SOURCE_LABEL,
      rowCount: cached.rows.length,
      url: cached.meta?.url,
      cachedAt: cached.meta?.timestamp,
      stale: true,
      sourceType: cached.meta?.sourceType ?? "cache"
    });
    showMessage(
      "Origine ripristinata dall'ultima copia salvata. Ricerca di aggiornamenti (file locale/remoto) in corso...",
      "warning"
    );
  }
  await loadSource(!cached);
});
