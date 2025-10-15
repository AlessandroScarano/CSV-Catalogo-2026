/* global Papa, saveAs */

// Stato globale dell'applicazione
const state = {
  sourceRows: [],
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
 * @param {File} file
 * @returns {Promise<object[]>}
 */
function parseSourceCsv(file) {
  return new Promise((resolve, reject) => {
    Papa.parse(file, {
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
    showMessage("Carica prima un CSV di origine", "error");
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
 * Aggiorna le informazioni del file caricato.
 * @param {File|null} file
 * @param {number} rowCount
 */
function updateFileInfo(file, rowCount) {
  const nameEl = document.getElementById("file-name");
  const countEl = document.getElementById("row-count");
  const reloadBtn = document.getElementById("reload-btn");
  if (file) {
    nameEl.textContent = file.name;
    countEl.textContent = `${rowCount} righe`;
    reloadBtn.disabled = false;
  } else {
    nameEl.textContent = "Nessun file caricato";
    countEl.textContent = "0 righe";
    reloadBtn.disabled = true;
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
 * Inizializza gli event listener dell'interfaccia.
 */
function setupListeners() {
  const fileInput = document.getElementById("csv-input");
  const reloadBtn = document.getElementById("reload-btn");
  const addBtn = document.getElementById("add-btn");
  const skuInput = document.getElementById("sku-input");
  const exportBtn = document.getElementById("export-btn");
  const clearBtn = document.getElementById("clear-btn");
  const downloadTemplateBtn = document.getElementById("download-template");

  fileInput.addEventListener("change", async (event) => {
    const [file] = event.target.files;
    if (!file) return;
    try {
      const rows = await parseSourceCsv(file);
      state.sourceRows = rows;
      state.columnMap = findColumns(rows[0]);
      if (!state.columnMap.sku) {
        throw new Error("Colonna SKU non trovata. Verifica l'header del file.");
      }
      if (!state.columnMap.parent) {
        throw new Error("Colonna Parent SKU non trovata. Verifica l'header del file.");
      }
      updateFileInfo(file, rows.length);
      showMessage(`CSV caricato (${rows.length} righe)`, "success");
    } catch (error) {
      console.error(error);
      state.sourceRows = [];
      state.columnMap = null;
      fileInput.value = "";
      updateFileInfo(null, 0);
      showMessage(error.message || "Errore durante il caricamento del CSV", "error");
    }
  });

  reloadBtn.addEventListener("click", () => {
    state.sourceRows = [];
    state.columnMap = null;
    fileInput.value = "";
    updateFileInfo(null, 0);
    showMessage("File rimosso. Carica un nuovo CSV.", "success");
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
window.addEventListener("DOMContentLoaded", () => {
  restoreModelRows();
  renderTable();
  setupListeners();
});

// CSV di esempio (commento) per test rapidi:
// sku;parent_sku;post_title;categoria;regular_price;meta:attribute_pa_finitura;dimensione;"per vetro";materiale;um
// PARENT1;;Titolo Parent 1;Categoria A;1234,56;Finitura base;L;Si;Acciaio;PZ
// VAR1;PARENT1;Variante 1;Categoria A;1234,56;Finitura 1;L;Si;Acciaio;PZ
// VAR2;PARENT1;Variante 2;Categoria A;1.234,56;Finitura 2;L;Si;Acciaio;PZ
// VAR3;PARENT1;Variante 3;Categoria A;1234.56;Finitura 3;L;Si;Acciaio;PZ
// PARENT2;;Titolo Parent 2;Categoria B;2000;Finitura base;M;No;Alluminio;CF
// VAR4;PARENT2;Variante 4;Categoria B;1999,99;Finitura 4;M;No;Alluminio;CF
