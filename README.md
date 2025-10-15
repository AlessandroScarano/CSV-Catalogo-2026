# CSV Catalogo 2026

Questa applicazione è una Single Page Application completamente client-side che utilizza il file CSV "padre + varianti" ufficiale del Catalogo 2026. All'avvio cerca prima una copia locale (`origineCat2026.csv`) accanto alla pagina e, se assente, prova a scaricare lo stesso file dal server remoto, per poi permettere la ricerca dello SKU e la generazione del modello finale.

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

> In alternativa puoi aprire direttamente `index.html` con il browser, ma alcuni browser limitano l'accesso ai file locali: usa comunque un server statico per consentire alla pagina di leggere `origineCat2026.csv` e, se necessario, scaricare la copia remota.

## Preparare l'origine

1. Recupera il file ufficiale `origineCat2026.csv` e copialo nella stessa cartella di `index.html` e `app.js`.
2. All'avvio l'app leggerà questa copia locale (nessun upload manuale necessario).
3. Se il file locale non è presente o non è accessibile, verrà tentato automaticamente il download dall'URL ufficiale `https://www.glasscom.it/Catalogo2026/origineCat2026.csv` (e, quando la pagina non è servita in HTTPS, anche dalla variante `http://`).
4. L'ultima versione caricata viene memorizzata nel browser per avvii successivi (anche se il file o la rete non sono disponibili).

## Come testare le funzionalità

1. **Sezione "Origine Catalogo 2026"**
   - Se il file `origineCat2026.csv` è presente accanto alla pagina, viene caricato immediatamente.
   - In mancanza del file locale, l'app tenta di scaricare il CSV dall'URL ufficiale indicato sopra.
   - Se hai già aperto l'app in precedenza, la copia salvata nel browser viene ripristinata e poi aggiornata appena possibile.
   - Il pannello mostra nome, numero di righe, tipo di fonte e ultima data di aggiornamento; usa "Ricarica origine" per forzare un nuovo tentativo (locale e poi remoto).

2. **Sezione "Cerca codice"**
   - Inserisci uno SKU padre o variante e premi "Aggiungi".
   - Il gruppo corrispondente viene trasformato in una singola riga e aggiunto all'anteprima.

3. **Sezione "Anteprima modello finale"**
   - Verifica la riga generata, rimuovila se necessario o controlla il badge che segnala varianti oltre le cinque.

4. **Sezione "Azioni"**
   - Usa "Esporta CSV" per scaricare il cumulativo nel formato richiesto.
   - "Svuota anteprima" cancella tutte le righe accumulate.

## Origine locale e remota

- Il file locale ha priorità: se presente, viene sempre utilizzato e puoi aggiornarlo semplicemente sostituendo il CSV nella cartella.
- Se il file locale non è accessibile, l'app tenta il download dell'origine remota passando prima da HTTPS e, quando possibile, dalla variante HTTP (può fallire se il server non espone gli header CORS necessari o se il browser blocca contenuti misti).
- Dopo il primo caricamento valido, il CSV viene memorizzato nel browser per velocizzare l'avvio successivo e gestire eventuali errori momentanei.
- Il pulsante **"Ricarica origine"** riesegue i tentativi in ordine (locale → remoto) e aggiorna la cache se viene trovata una sorgente valida.

## Limitazioni note

- Vengono considerate al massimo cinque varianti per ogni SKU padre (le successive vengono ignorate con messaggio informativo).
- Alcuni browser bloccano l'accesso diretto ai file locali quando la pagina è aperta da `file://`: avvia un piccolo server statico per permettere la lettura di `origineCat2026.csv`.
- Se servi l'applicazione in HTTPS, il fallback HTTP dell'origine remota verrà bloccato dal browser: assicurati di avere la copia locale oppure abilita una sorgente remota con HTTPS valido.
- Se non stai usando un server statico, il file locale potrebbe non essere leggibile e il browser segnalerà l'errore "Failed to fetch".
- Se appare il messaggio "Libreria Papa Parse non disponibile", controlla la connessione internet o eventuali blocchi verso le CDN utilizzate (jsDelivr).
- Le colonne del CSV vengono rilevate con nomi tolleranti, ma intestazioni completamente assenti o molto atipiche potrebbero richiedere un adattamento manuale del file sorgente.

Buon lavoro!
