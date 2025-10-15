# CSV Catalogo 2026

Questa repository contiene due soluzioni complementari per lavorare con il Catalogo 2026:

1. **SPA client-side** (file `index.html`, `app.js`, `styles.css`): funziona interamente nel browser e prova a caricare l'origine `origineCat2026.csv` da locale, dall'URL ufficiale e infine da mirror pubblici. È ideale quando puoi pubblicare la pagina su un server statico e non vuoi installare nulla sul computer dell'utente.
2. **Applicazione Streamlit** (file `streamlit_app.py`): offre la stessa logica di ricerca e rimappatura ma con un piccolo backend Python che elimina i problemi di CORS e di caricamento da `file://`. È consigliata quando desideri un'esperienza stabile senza dipendere da CDN o policy del browser.

Di seguito trovi le istruzioni per entrambe le modalità.

## Modalità SPA (client-side)

### Come eseguire l'applicazione

1. **Clona o scarica** il progetto in una cartella locale.
2. Apri un terminale nella cartella del progetto.
3. Avvia un piccolo server statico. È indispensabile: i browser, quando la pagina è aperta da `file://`, bloccano il caricamento automatico di `origineCat2026.csv`.
   - Con Python 3 (disponibile su macOS, Linux e Windows):
     ```bash
     python -m http.server 8000
     ```
   - Con PowerShell su Windows:
     ```powershell
     py -m http.server 8000
     ```
   - Con Node.js (se installato):
     ```bash
     npx serve .
     ```
4. Apri il browser all'indirizzo [http://localhost:8000](http://localhost:8000) (sostituisci la porta se diversa) e vedrai l'interfaccia dell'applicazione.

> L'apertura diretta di `index.html` dal file system mostrerà un avviso e l'origine non verrà caricata: è un comportamento previsto dei browser moderni per motivi di sicurezza.

### Preparare l'origine

1. Recupera il file ufficiale `origineCat2026.csv` e copialo nella stessa cartella di `index.html` e `app.js`.
2. All'avvio l'app leggerà questa copia locale (nessun upload manuale necessario).
3. Se il file locale non è presente o non è accessibile, verrà tentato automaticamente il download dall'URL ufficiale `https://www.glasscom.it/Catalogo2026/origineCat2026.csv` (e, quando la pagina non è servita in HTTPS, anche dalla variante `http://`). Qualora il server remoto blocchi la richiesta CORS, l'app utilizza un mirror pubblico (`https://r.jina.ai`) per recuperare lo stesso file.
4. L'ultima versione caricata viene memorizzata nel browser per avvii successivi (anche se il file o la rete non sono disponibili).

### Come testare le funzionalità (SPA)

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

### Origine locale e remota (SPA)

- Il file locale ha priorità: se presente, viene sempre utilizzato e puoi aggiornarlo semplicemente sostituendo il CSV nella cartella.
- Se il file locale non è accessibile, l'app tenta il download dell'origine remota passando prima da HTTPS e, quando possibile, dalla variante HTTP. Se entrambi i tentativi sono bloccati dal server (assenza di CORS), viene utilizzato automaticamente il mirror `https://r.jina.ai`, che fornisce gli header necessari.
- Dopo il primo caricamento valido, il CSV viene memorizzato nel browser per velocizzare l'avvio successivo e gestire eventuali errori momentanei.
- Il pulsante **"Ricarica origine"** riesegue i tentativi in ordine (locale → remoto) e aggiorna la cache se viene trovata una sorgente valida.

### Limitazioni note (SPA)

- Vengono considerate al massimo cinque varianti per ogni SKU padre (le successive vengono ignorate con messaggio informativo).
- L'apertura diretta da `file://` impedisce alla pagina di leggere `origineCat2026.csv`. Utilizza sempre un server statico locale (anche temporaneo) per lavorare con la copia accanto alla pagina.
- Se servi l'applicazione in HTTPS, il fallback HTTP dell'origine remota verrà bloccato dal browser, ma il mirror `https://r.jina.ai` continuerà a funzionare perché fornisce il file tramite HTTPS.
- Il mirror pubblico richiede comunque una connessione a Internet; in assenza di rete verrà utilizzata l'ultima copia salvata nella cache locale.
- Se non stai usando un server statico, il file locale potrebbe non essere leggibile e il browser segnalerà l'errore "Failed to fetch".
- Se appare il messaggio "Libreria Papa Parse non disponibile", controlla la connessione internet o eventuali blocchi verso le CDN utilizzate (jsDelivr).
- Le colonne del CSV vengono rilevate con nomi tolleranti, ma intestazioni completamente assenti o molto atipiche potrebbero richiedere un adattamento manuale del file sorgente.

## Modalità consigliata alternativa: Streamlit

Quando non puoi (o non vuoi) appoggiarti alla tecnologia puramente client-side, puoi utilizzare la versione con backend leggero inclusa nel file `streamlit_app.py`. Streamlit gestisce il caricamento del CSV lato server locale, perciò il browser non incontra più blocchi CORS e non è necessario esporre il file pubblico su Internet.

### Requisiti

- Python 3.9 o superiore.
- Dipendenze Python indicate nel file `requirements.txt` (installabile con `pip`).
- Il file `origineCat2026.csv` nella stessa cartella del progetto, oppure una connessione Internet verso l'URL ufficiale.

### Installazione e avvio

```bash
python -m venv .venv
source .venv/bin/activate  # su Windows: .venv\\Scripts\\activate
pip install -r requirements.txt
streamlit run streamlit_app.py
```

Il comando avvia un server locale (di default su [http://localhost:8501](http://localhost:8501)). Apri l'indirizzo nel browser: l'applicazione mostrerà subito lo stato dell'origine e consentirà la ricerca degli SKU.

### Utilizzo

1. Verifica nel pannello laterale la fonte caricata (locale, HTTPS, HTTP o mirror) e il numero di righe disponibili.
2. Inserisci uno SKU padre o variante nel campo "Cerca codice" e premi **Aggiungi riga**.
3. L'anteprima mostrerà la riga del modello finale; puoi aggiungere più codici e, se necessario, usare **Svuota anteprima**.
4. Con **Esporta CSV** generi il file finale con separatore `;`, pronto per l'import nel gestionale.
5. Il pulsante **Scarica modello vuoto** permette di ottenere rapidamente l'header del modello.

### Perché scegliere Streamlit

- Il CSV viene caricato una sola volta al lancio e rimane disponibile lato server.
- Nessun vincolo legato alle policy del browser (niente CORS, niente blocchi `file://`).
- Possibilità di evolvere l'applicazione con funzionalità Python (filtri avanzati, validazioni, log) senza toccare il front-end.
- L'esportazione CSV avviene dal backend, evitando problemi di encoding e formato.

## Quale soluzione scegliere?

- **Usa la SPA** se devi distribuire una pagina statica a più utenti e puoi pubblicarla su un hosting che esponga anche il CSV (o che consenta il mirror). Richiede zero installazione lato utente finale ma dipende dalle restrizioni del browser.
- **Usa l'app Streamlit** se lavori in locale o in un contesto aziendale dove puoi avviare un piccolo servizio interno. È più affidabile, sempre disponibile offline con il CSV accanto ai file sorgente e non soffre di problemi di sicurezza CORS.

In entrambi i casi puoi partire da questo repository e scegliere la modalità più adatta al tuo flusso di lavoro quotidiano.

Buon lavoro!
