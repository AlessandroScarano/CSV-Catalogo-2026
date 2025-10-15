/* global Papa, saveAs */

const REMOTE_SOURCE_URL = "https://www.glasscom.it/Catalogo2026/origineCat2026.csv";
const REMOTE_SOURCE_NAME = "Origine Catalogo 2026";
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
function persistSourceCache(text, rowCount) {
  try {
    const storage = window.localStorage;
    if (!storage) return;
    storage.setItem(SOURCE_CACHE_TEXT_KEY, text);
    storage.setItem(
      SOURCE_CACHE_META_KEY,
      JSON.stringify({ rowCount, timestamp: Date.now() })
    );
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
    nameEl.textContent = `${meta.name}${meta.url ? ` (${meta.url})` : ""}`;
    countEl.textContent = `${meta.rowCount} righe`;
    const details = [];
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
 * Salva la sorgente del CSV in localStorage.
 * @param {object[]} rows
 * @param {{name: string, rowCount: number, persisted?: boolean}} meta
 */
async function loadRemoteSource(showNotification = true) {
  const nameEl = document.getElementById("file-name");
  const countEl = document.getElementById("row-count");
  const reloadBtn = document.getElementById("reload-btn");
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
    const response = await fetch(REMOTE_SOURCE_URL, {
      cache: "no-cache",
      mode: "cors",
      credentials: "omit"
    });
    if (!response.ok) {
      throw new Error(`Impossibile scaricare l'origine remota (${response.status})`);
    }
    const text = await response.text();
    const rows = await parseSourceCsv(text);
    const columnMap = findColumns(rows[0]);
    if (!columnMap?.sku || !columnMap?.parent) {
      throw new Error("Colonne essenziali mancanti nell'origine remota.");
    }
    persistSourceCache(text, rows.length);
    applySource(rows, columnMap, {
      name: REMOTE_SOURCE_NAME,
      rowCount: rows.length,
      url: REMOTE_SOURCE_URL,
      cachedAt: Date.now(),
      stale: false
    });
    if (showNotification) {
      showMessage(`Origine remota caricata (${rows.length} righe)`, "success");
    }
  } catch (error) {
    console.error(error);
    const cached = await getCachedSource();
    if (cached) {
      applySource(cached.rows, cached.columnMap, {
        name: REMOTE_SOURCE_NAME,
        rowCount: cached.rows.length,
        url: REMOTE_SOURCE_URL,
        cachedAt: cached.meta?.timestamp,
        stale: true
      });
      const message = error.message
        ? `Impossibile aggiornare l'origine remota: ${error.message}. È stata utilizzata l'ultima copia salvata.`
        : "Impossibile aggiornare l'origine remota. È stata utilizzata l'ultima copia salvata.";
      showMessage(message, "warning");
    } else {
      state.sourceRows = [];
      state.columnMap = null;
      state.sourceMeta = null;
      updateFileInfo(null);
      const message = error.message
        ? `Origine non disponibile: ${error.message}`
        : "Origine non disponibile. Controlla la connessione e riprova.";
      showMessage(message, "error");
    }
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
    loadRemoteSource();
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
      name: REMOTE_SOURCE_NAME,
      rowCount: cached.rows.length,
      url: REMOTE_SOURCE_URL,
      cachedAt: cached.meta?.timestamp,
      stale: true
    });
    showMessage("Origine ripristinata dall'ultima copia salvata. Aggiornamento in corso...", "warning");
  }
  await loadRemoteSource(!cached);
});
