"""Streamlit application for mapping Catalogo 2026 SKUs.

This alternative implementation avoids browser CORS issues by running a
small local web app with a Python backend. It loads the origin CSV once on
startup and lets the user search for SKUs, aggregate rows, and export the
remapped model as a CSV with the required schema.
"""

from __future__ import annotations

import io
from datetime import datetime
from pathlib import Path
from typing import Dict, List, Optional, Sequence, Tuple

import pandas as pd
import streamlit as st

SCHEMA: Sequence[str] = [
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
    "@image_SchedeTecniche",
]

LOCAL_SOURCE = Path("origineCat2026.csv")
REMOTE_SOURCES = [
    ("HTTPS", "https://www.glasscom.it/Catalogo2026/origineCat2026.csv"),
    ("HTTP", "http://www.glasscom.it/Catalogo2026/origineCat2026.csv"),
    (
        "Mirror",
        "https://r.jina.ai/https://www.glasscom.it/Catalogo2026/origineCat2026.csv",
    ),
]


def _find_column(columns: Sequence[str], candidates: Sequence[str]) -> Optional[str]:
    """Return the first column whose name matches one of the candidates."""

    lower_columns = [col.lower() for col in columns]
    for candidate in candidates:
        needle = candidate.lower()
        for idx, col_lower in enumerate(lower_columns):
            if col_lower == needle:
                return columns[idx]
        for idx, col_lower in enumerate(lower_columns):
            if needle in col_lower:
                return columns[idx]
    return None


def _find_columns(columns: Sequence[str]) -> Dict[str, Optional[str]]:
    """Locate the relevant column names in the origin dataset."""

    return {
        "sku": _find_column(columns, ["sku"]),
        "parent": _find_column(columns, ["parent_sku", "parent sku", "parent"]),
        "title": _find_column(columns, ["post_title", "titolo"]),
        "cat": _find_column(columns, ["categoria", "product_cat"]),
        "price": _find_column(columns, ["regular_price", "price"]),
        "fin": _find_column(columns, ["meta:attribute_pa_finitura", "finitura"]),
        "um": _find_column(columns, ["um"]),
        "mat": _find_column(columns, ["materiale"]),
        "dim": _find_column(columns, ["dimensione", "size"]),
        "glass": _find_column(columns, ["per vetro", "vetro"]),
    }


def _normalize_price(value: Optional[str]) -> str:
    """Convert price strings to a canonical form with two decimals."""

    if value is None:
        return ""
    text = str(value).strip()
    if not text:
        return ""

    # Remove currency symbols and spaces.
    for token in ["€", "EUR", "euro"]:
        text = text.replace(token, "")
    text = text.replace(" ", "")

    if not text:
        return ""

    # Handle thousand separators and decimal marks.
    if "," in text and "." in text:
        text = text.replace(".", "").replace(",", ".")
    elif "," in text:
        text = text.replace(",", ".")

    try:
        number = float(text)
    except ValueError:
        return ""

    return f"{number:.2f}"


def _is_parent_empty(row: Dict[str, str], parent_key: Optional[str]) -> bool:
    """Return True when the parent SKU value is empty or represents null."""

    if not parent_key:
        return True
    value = row.get(parent_key, "")
    text = str(value).strip().lower()
    return text in {"", "nan", "none", "null"}


def _index_of_main_for_code(
    code: str, rows: Sequence[Dict[str, str]], col_map: Dict[str, Optional[str]]
) -> Optional[int]:
    """Find the index of the main row corresponding to the provided SKU."""

    sku_key = col_map.get("sku")
    parent_key = col_map.get("parent")
    if not sku_key:
        return None

    needle = code.strip()
    for idx, row in enumerate(rows):
        sku = str(row.get(sku_key, "")).strip()
        if sku != needle:
            continue
        if _is_parent_empty(row, parent_key):
            return idx
        cursor = idx - 1
        while cursor >= 0:
            if _is_parent_empty(rows[cursor], parent_key):
                return cursor
            cursor -= 1
        return None
    return None


def _collect_group(
    start_index: int, rows: Sequence[Dict[str, str]], col_map: Dict[str, Optional[str]]
) -> Tuple[Dict[str, str], List[Dict[str, str]]]:
    """Collect the main row and its variants starting from start_index."""

    parent_key = col_map.get("parent")
    main_row = rows[start_index]
    variants: List[Dict[str, str]] = []
    for row in rows[start_index + 1 :]:
        if _is_parent_empty(row, parent_key):
            break
        variants.append(row)
    return main_row, variants


def _build_model_row(
    main: Dict[str, str],
    variants: Sequence[Dict[str, str]],
    col_map: Dict[str, Optional[str]],
) -> Tuple[Dict[str, str], int]:
    """Create the remapped row for the final CSV schema."""

    sku_key = col_map.get("sku")
    title_key = col_map.get("title")
    cat_key = col_map.get("cat")
    price_key = col_map.get("price")
    fin_key = col_map.get("fin")
    um_key = col_map.get("um")
    mat_key = col_map.get("mat")
    dim_key = col_map.get("dim")
    glass_key = col_map.get("glass")

    codice_articolo = str(main.get(sku_key, "")).strip() if sku_key else ""

    row = {key: "" for key in SCHEMA}
    row["Prodotto"] = ""
    row["Sottotitolo"] = ""
    row["Categoria"] = str(main.get(cat_key, "")).strip() if cat_key else ""
    row["Nome Articolo"] = str(main.get(title_key, "")).strip() if title_key else ""
    row["Codice Articolo"] = codice_articolo
    row["@image_01"] = f"singoli-componenti/images/{codice_articolo}.png" if codice_articolo else ""
    row["@image_scheda"] = f"singoli-componenti/pdf/{codice_articolo}.pdf" if codice_articolo else ""
    row["Dimensione"] = str(main.get(dim_key, "")).strip() if dim_key else ""
    row["Per Vetro"] = str(main.get(glass_key, "")).strip() if glass_key else ""
    row["Materiale"] = str(main.get(mat_key, "")).strip() if mat_key else ""
    row["UM"] = str(main.get(um_key, "")).strip() if um_key else ""
    row["@image_SchedeTecniche"] = ""

    extra_variants = max(0, len(variants) - 5)
    for index, variant in enumerate(variants[:5], start=1):
        sku_variant = str(variant.get(sku_key, "")).strip() if sku_key else ""
        row[f"cod{index}"] = sku_variant
        if price_key:
            row[f"Prezzo_cod{index}"] = _normalize_price(variant.get(price_key))
        row[f"fin{index}"] = str(variant.get(fin_key, "")).strip() if fin_key else ""
        row[f"@image_fin{index}"] = (
            f"singoli-componenti/images/{sku_variant}.png" if sku_variant else ""
        )

    return row, extra_variants


def load_origin() -> Tuple[List[Dict[str, str]], Dict[str, Optional[str]], str]:
    """Load the origin CSV from disk or remote fallbacks."""

    attempts: List[Tuple[str, Optional[pd.DataFrame], Optional[str]]] = []

    if LOCAL_SOURCE.exists():
        try:
            df = pd.read_csv(LOCAL_SOURCE, sep=";", dtype=str, keep_default_na=False)
            attempts.append(("Locale", df, None))
        except Exception as exc:  # pragma: no cover - diagnostic path
            attempts.append(("Locale", None, str(exc)))

    for label, url in REMOTE_SOURCES:
        try:
            df = pd.read_csv(url, sep=";", dtype=str, keep_default_na=False)
            attempts.append((label, df, None))
        except Exception as exc:  # pragma: no cover - diagnostic path
            attempts.append((label, None, str(exc)))

    for label, df, error in attempts:
        if df is None or df.empty:
            continue
        df = df.fillna("")
        df = df.replace({pd.NA: ""})
        df = df[[col for col in df.columns]]
        df = df.loc[~df.apply(lambda row: all(str(val).strip() == "" for val in row), axis=1)]
        rows = df.to_dict("records")
        col_map = _find_columns(df.columns.tolist())
        if not col_map.get("sku"):
            continue
        source_label = label
        return rows, col_map, source_label

    errors = [f"{label}: {error or 'dataset vuoto'}" for label, _, error in attempts]
    raise RuntimeError("Impossibile caricare l'origine.\n" + "\n".join(errors))


@st.cache_data(show_spinner=True)
def get_origin() -> Tuple[List[Dict[str, str]], Dict[str, Optional[str]], str, str]:
    """Load and cache the origin dataset for the session."""

    rows, col_map, source_label = load_origin()
    loaded_at = datetime.now().strftime("%d/%m/%Y %H:%M:%S")
    return rows, col_map, source_label, loaded_at


def _ensure_session_state() -> None:
    """Initialise session-state containers."""

    if "model_rows" not in st.session_state:
        st.session_state.model_rows: List[Dict[str, str]] = []
    if "messages" not in st.session_state:
        st.session_state.messages: List[Tuple[str, str]] = []
    if "export_payload" not in st.session_state:
        st.session_state.export_payload: Optional[bytes] = None


def _add_message(level: str, text: str) -> None:
    """Store feedback messages to be rendered after interactions."""

    st.session_state.messages.append((level, text))


def _flush_messages() -> None:
    """Render and clear queued messages."""

    for level, text in st.session_state.messages:
        if level == "success":
            st.success(text)
        elif level == "warning":
            st.warning(text)
        else:
            st.error(text)
    st.session_state.messages.clear()


def add_by_code(code: str, rows: Sequence[Dict[str, str]], col_map: Dict[str, Optional[str]]) -> None:
    """Add a row to the preview based on the provided SKU code."""

    trimmed = code.strip()
    if not trimmed:
        _add_message("error", "Inserisci un codice valido prima di aggiungere.")
        return

    if any(row["Codice Articolo"] == trimmed for row in st.session_state.model_rows):
        _add_message("warning", f"Il codice {trimmed} è già presente nell'anteprima.")
        return

    main_index = _index_of_main_for_code(trimmed, rows, col_map)
    if main_index is None:
        _add_message("error", f"Codice {trimmed} non trovato nell'origine.")
        return

    main_row, variants = _collect_group(main_index, rows, col_map)
    model_row, extra = _build_model_row(main_row, variants, col_map)
    st.session_state.model_rows.append(model_row)
    st.session_state.export_payload = None
    _add_message("success", f"Aggiunta la riga per {model_row['Codice Articolo']}")
    if extra:
        _add_message(
            "warning",
            f"Varianti oltre le prime 5 ignorate per {model_row['Codice Articolo']}: {extra} escluse.",
        )


def clear_all() -> None:
    """Reset the accumulated preview rows."""

    st.session_state.model_rows.clear()
    st.session_state.export_payload = None
    _add_message("success", "Anteprima svuotata.")


def export_csv() -> Optional[bytes]:
    """Generate the CSV content for download."""

    if not st.session_state.model_rows:
        _add_message("error", "Anteprima vuota: aggiungi almeno una riga prima di esportare.")
        return None

    df = pd.DataFrame(st.session_state.model_rows, columns=SCHEMA)
    buffer = io.StringIO()
    df.to_csv(buffer, sep=";", index=False)
    return buffer.getvalue().encode("utf-8")


def main() -> None:
    """Streamlit entry point."""

    st.set_page_config(page_title="Catalogo 2026 Mapper", layout="wide")
    _ensure_session_state()

    st.title("Catalogo 2026 – Mapper SKU")
    st.write(
        "Questa versione con backend locale evita i blocchi del browser: avvia il "
        "comando `streamlit run streamlit_app.py`, assicurandoti che il file "
        "`origineCat2026.csv` sia nella stessa cartella."
    )

    try:
        rows, col_map, source_label, loaded_at = get_origin()
    except Exception as exc:
        st.error(f"Errore nel caricamento dell'origine: {exc}")
        return

    st.sidebar.header("Origine")
    st.sidebar.write(f"Sorgente: **{source_label}**")
    st.sidebar.write(f"Righe disponibili: **{len(rows)}**")
    st.sidebar.write(f"Caricato alle: {loaded_at}")

    missing_keys = [key for key in ("sku", "title", "cat") if not col_map.get(key)]
    if missing_keys:
        st.error(
            "Colonne fondamentali mancanti nell'origine: "
            + ", ".join(missing_keys)
            + ". Aggiorna il CSV o mappa manualmente le colonne."
        )
        return

    with st.form("add-form", clear_on_submit=True):
        st.subheader("Cerca codice")
        code = st.text_input("Inserisci codice (SKU padre o variante)")
        submitted = st.form_submit_button("Aggiungi riga")
        if submitted:
            add_by_code(code, rows, col_map)

    st.subheader("Anteprima modello finale")
    if st.session_state.model_rows:
        preview_df = pd.DataFrame(st.session_state.model_rows, columns=SCHEMA)
        st.dataframe(preview_df, use_container_width=True)
    else:
        st.info("Anteprima vuota: aggiungi almeno un codice.")

    st.write(f"Righe in anteprima: **{len(st.session_state.model_rows)}**")

    col1, col2, col3 = st.columns(3)
    with col1:
        if col1.button("Prepara export"):
            payload = export_csv()
            if payload is not None:
                st.session_state.export_payload = payload
        download_disabled = st.session_state.export_payload is None
        st.download_button(
            "Scarica modello_finale.csv",
            data=(
                io.BytesIO(st.session_state.export_payload)
                if st.session_state.export_payload is not None
                else None
            ),
            file_name="modello_finale.csv",
            mime="text/csv",
            disabled=download_disabled,
        )
    with col2:
        if col2.button("Svuota anteprima"):
            clear_all()
    with col3:
        st.download_button(
            "Scarica modello vuoto",
            data=io.BytesIO(";".join(SCHEMA).encode("utf-8")),
            file_name="modello_vuoto.csv",
            mime="text/csv",
        )

    st.divider()
    st.markdown(
        """### Come testare
1. Copia `origineCat2026.csv` accanto a questo file oppure assicurati che l'URL remoto sia raggiungibile.
2. Avvia `streamlit run streamlit_app.py` in un ambiente con `pandas` e `streamlit` installati.
3. Inserisci un codice padre o variante per popolare l'anteprima.
4. Usa i pulsanti per esportare o svuotare l'anteprima.
"""
    )

    _flush_messages()


if __name__ == "__main__":
    main()
