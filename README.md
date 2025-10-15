# CSV Catalogo 2026

Questa applicazione è una Single Page Application completamente client-side che scarica automaticamente il file CSV "padre + varianti" ufficiale del Catalogo 2026, permette di cercare uno SKU e rimappare il gruppo nel modello finale per l'esportazione.

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

> In alternativa puoi aprire direttamente `index.html` con il browser, ma alcuni browser limitano l'accesso ai file locali: se il download dell'origine remota non funziona, usa un server statico come sopra.

## Come testare le funzionalità

1. **Sezione "Origine Catalogo 2026"**
   - All'avvio viene scaricato il file ufficiale da `https://www.glasscom.it/Catalogo2026/origineCat2026.csv`.
   - Se hai già aperto l'app in precedenza, la copia salvata localmente viene ripristinata immediatamente e aggiornata in background.
   - Il pannello mostra nome, numero di righe e ultima data di aggiornamento; usa "Ricarica origine" per forzare un nuovo download.

2. **Sezione "Cerca codice"**
   - Inserisci uno SKU padre o variante e premi "Aggiungi".
   - Il gruppo corrispondente viene trasformato in una singola riga e aggiunto all'anteprima.

3. **Sezione "Anteprima modello finale"**
   - Verifica la riga generata, rimuovila se necessario o controlla il badge che segnala varianti oltre le cinque.

4. **Sezione "Azioni"**
   - Usa "Esporta CSV" per scaricare il cumulativo nel formato richiesto.
   - "Svuota anteprima" cancella tutte le righe accumulate.

## CSV di esempio

Se vuoi fare test offline puoi salvare manualmente il contenuto dell'origine ufficiale oppure crearne uno personalizzato, ma l'applicazione in produzione utilizza sempre l'URL remoto indicato sopra.

## Origine remota

- L'origine è sempre scaricata dall'URL indicato e non è possibile sostituirla tramite upload locale.
- Dopo il primo download, il CSV viene memorizzato nel browser per velocizzare l'avvio successivo.
- Il pulsante **"Ricarica origine"** forza un nuovo download in caso di aggiornamenti sul server.
- In caso di errori di rete viene utilizzata automaticamente l'ultima copia salvata; se non disponibile, viene mostrato un messaggio d'errore.

## Limitazioni note

- Vengono considerate al massimo cinque varianti per ogni SKU padre (le successive vengono ignorate con messaggio informativo).
- Alcuni browser potrebbero bloccare l'accesso ai file locali se la pagina non è servita tramite HTTP.
- Se appare il messaggio "Libreria Papa Parse non disponibile", controlla la connessione internet o eventuali blocchi verso le CDN utilizzate (jsDelivr).
- Le colonne del CSV vengono rilevate con nomi tolleranti, ma intestazioni completamente assenti o molto atipiche potrebbero richiedere un adattamento manuale del file sorgente.

Buon lavoro!
