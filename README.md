# CSV Catalogo 2026

Questa applicazione è una Single Page Application completamente client-side che consente di caricare un file CSV "padre + varianti", cercare uno SKU e rimappare il gruppo nel modello finale per l'esportazione.

## Come eseguire l'applicazione

1. **Clona o scarica** il progetto in una cartella locale.
2. Apri un terminale nella cartella del progetto.
3. Avvia un piccolo server statico (consigliato per evitare restrizioni dei browser sui file locali). Ad esempio:
   - Con Python 3:
     ```bash
     python -m http.server 8000
     ```
   - Oppure con Node.js (se installato):
     ```bash
     npx serve .
     ```
4. Apri il browser all'indirizzo [http://localhost:8000](http://localhost:8000) (sostituisci la porta se diversa) e vedrai l'interfaccia dell'applicazione.

> In alternativa puoi aprire direttamente `index.html` con il browser, ma alcuni browser limitano l'accesso ai file locali: se l'upload del CSV non funziona, usa un server statico come sopra.

## Come testare le funzionalità

1. **Sezione "Carica CSV di origine"**
   - All'avvio viene caricata automaticamente un'origine di esempio incorporata: puoi usarla subito per fare delle prove.
   - Premi "Seleziona file" e scegli un CSV con separatore `;` e intestazioni per sostituire l'origine.
   - Dopo il caricamento vengono mostrati nome file e numero di righe e la sorgente viene salvata nel browser (localStorage).

2. **Sezione "Cerca codice"**
   - Inserisci uno SKU padre o variante e premi "Aggiungi".
   - Il gruppo corrispondente viene trasformato in una singola riga e aggiunto all'anteprima.

3. **Sezione "Anteprima modello finale"**
   - Verifica la riga generata, rimuovila se necessario o controlla il badge che segnala varianti oltre le cinque.

4. **Sezione "Azioni"**
   - Usa "Esporta CSV" per scaricare il cumulativo nel formato richiesto.
   - "Svuota anteprima" cancella tutte le righe accumulate.

## CSV di esempio

Puoi copiare il contenuto seguente in un file `esempio.csv` per fare una prova rapida:

```
sku;parent_sku;post_title;categoria;regular_price;meta:attribute_pa_finitura;dimensione;per vetro;materiale;um
MAIN001;;Titolo Prodotto A;Categoria X;1.234,56;Finitura Oro;Grande;Sì;Metallo;PZ
VAR001;MAIN001;Titolo Variante 1;Categoria X;950,50;Finitura Argento;Grande;Sì;Metallo;PZ
VAR002;MAIN001;Titolo Variante 2;Categoria X;860;Finitura Bronzo;Grande;Sì;Metallo;PZ
MAIN002;;Titolo Prodotto B;Categoria Y;1350,00;Finitura Legno;Piccolo;No;Legno;PZ
VAR003;MAIN002;Titolo Variante 3;Categoria Y;1200,75;Finitura Nera;Piccolo;No;Legno;PZ
```

Carica il file, inserisci ad esempio `MAIN001` o `VAR002` e verifica che venga generata la riga in anteprima.

## Origine persistente e reset

- L'origine caricata viene salvata in localStorage: al prossimo accesso non dovrai ricaricare il file.
- Usa il pulsante **"Rimuovi / ricarica"** per cancellare la sorgente salvata e tornare al dataset predefinito.
- Per distribuire un'origine personalizzata sempre disponibile puoi sostituire il dataset incorporato in `app.js` (`EMBEDDED_SOURCE`).

## Limitazioni note

- Vengono considerate al massimo cinque varianti per ogni SKU padre (le successive vengono ignorate con messaggio informativo).
- Alcuni browser potrebbero bloccare l'accesso ai file locali se la pagina non è servita tramite HTTP.
- Se appare il messaggio "Libreria Papa Parse non disponibile", controlla la connessione internet o eventuali blocchi verso le CDN utilizzate (jsDelivr).
- Le colonne del CSV vengono rilevate con nomi tolleranti, ma intestazioni completamente assenti o molto atipiche potrebbero richiedere un adattamento manuale del file sorgente.

Buon lavoro!
