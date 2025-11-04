# Interview Assistant — Project Plan

## One-line summary
A simple web application that acts as an interview assistant. It takes spoken or typed input, sends it to a large language model (LLM), and displays the response. It also keeps a history of conversations and allows for deeper research on responses using Perplexica.

## Goals and acceptance criteria
- UI: Clean, responsive page using Bootstrap 5 with a textarea, response area, and toggle button.
- Behavior: When the toggle is ON, user input is auto-sent a short while after typing (debounced). The textarea is cleared on send. Response is shown below the input.
- Backend: PHP endpoint that reads configuration from `.env` and forwards requests to either Ollama (local or remote) or OpenAI.
- Security: API keys live only in `.env` (never committed).
- Developer experience: Can run locally via PHP built-in server. Minimal dependencies (Bootstrap/jQuery via CDN).
- **Conversation History:** A separate page (`history.php`) that displays a timeline of all conversations.
- **Dig Deeper with Perplexica:** Allows users to send a response to a Perplexica instance for further research.
- **Conversational Memory:** Remembers the last few turns of the conversation to understand follow-up questions.
- **Topic Recognition:** Analyzes the conversation history to identify the main topic, displayed in a separate tab on the history page.
- **Conversation Report Generation:** Generates a downloadable HTML report summarizing the conversation, including a full transcript and identified keywords.
- **Topic Word Cloud:** Analyzes the entire conversation history to generate weighted topic data suitable for a word cloud visualization.
- **Streaming LLM Responses:** LLM responses are streamed to the user in real-time, improving perceived performance.
- **Responsive Navbar:** A responsive navigation bar has been added for easy navigation between the main pages.

## Assumptions
- The user will use Windows 11 voice assist to input text into the textarea (client-side). The app does not perform speech recognition — Windows handles that.
- LLM calls are proxied through PHP to avoid exposing API keys in the browser and to avoid CORS problems.
- Ollama may be hosted at a local HTTP endpoint (e.g., `http://127.0.0.1:11434`) or a remote Ollama-compatible endpoint. OpenAI will use their HTTP API.
- `shortwhile` debounce will be implemented as a configurable delay (default ~700ms).

## Contract (tiny)
- Input: user text string from textarea.
- Output: Server-Sent Events (SSE) for streaming responses.
- Error modes: network timeouts, provider error, invalid config.

## Edge cases to handle
- Empty input (do nothing).
- Very long input (truncate or return helpful message / let backend handle length limits).
- Provider down / 401 / 4xx / 5xx errors — report to client.
- Toggle OFF: no requests are sent.
- Rapid input: debounce prevents flooding provider.

## Architecture / File map
- `index.php` — Main application page with a chat-style interface for asking questions and seeing a scrollable history of responses.
- `history.php` — Page to display the conversation history and download reports.
- `api/ask.php` — PHP proxy to handle streaming LLM requests and save history.
- `api/history.php` — PHP endpoint to serve the conversation history.
- `api/perplexica.php` — PHP proxy to handle Perplexica requests.
- `api/topic.php` — PHP endpoint to analyze and return the current conversation topic.
- `api/topics.php` — PHP endpoint that analyzes the entire history to generate weighted topic data for a word cloud.
- `api/generate_report.php` — PHP endpoint to generate and download a full conversation report in HTML format.
- `assets/js/app.js` — JavaScript for the main application page, including handling of streaming responses.
- `assets/js/history.js` — JavaScript for the history page.
- `assets/css/style.css` — Custom CSS styles.
- `.env.example` — Example environment variables file.
- `README.md` — Project documentation.
- `PLAN.md` — Initial project plan.
- `project.json` — This file, providing a structured overview of the project.

## Environment variables (`.env` / `.env.example`)
- PROVIDER=ollama|openai
- OLLAMA_URL=http://127.0.0.1:11434
- OLLAMA_MODEL=ggml-gpt4o (or your model)
- OPENAI_API_KEY=sk-...
- OPENAI_BASE_URL=https://api.openai.com (optional; defaults to OpenAI official API)
- DEBOUNCE_MS=700
- TIMEOUT_SECONDS=30
- PERPLEXICA_URL=http://127.0.0.1:8080

Note: Do not commit `.env`.

## API behavior (proxy)
- **Endpoint:** `POST /api/ask.php`
- **Request body (JSON):** `{ "conversation": [...] }`
- **Response (Server-Sent Events):** The endpoint streams the response using SSE. Each message is a JSON object with the following structure: `data: {"success":true,"response":"The LLM's response chunk."}`

- **Endpoint:** `GET /api/topic.php`
- **Response (JSON):** `{ "topic": "The identified topic of the conversation." }`

- **Endpoint:** `GET /api/topics.php`
- **Response (JSON):** An array of topics and their calculated weights, suitable for a word cloud. `[ [ "topic1", 10.5 ], [ "topic2", 8.0 ] ]`

- **Endpoint:** `GET /api/generate_report.php`
- **Response:** An HTML page attachment containing a full report of the conversation.

Provider implementation notes:
- Ollama: POST to `${OLLAMA_URL}/api/chat` with `{ model, messages, stream: true }`.
- OpenAI: POST to `${OPENAI_BASE_URL}/v1/chat/completions` with `{ model, messages, stream: true }`, include Authorization header `Bearer ${OPENAI_API_KEY}`.
- Use curl-like PHP client (cURL) with timeout and error handling.

## UI details
- Layout: centered card with textarea on top, inline toggle (Bootstrap switch), a small status indicator, and a response card below.
- Behavior:
  - Toggle OFF: textarea works locally but no requests are sent.
  - Toggle ON: on `input` event start/restart debounce timer (DEBOUNCE_MS). When timer fires and input is non-empty:
    - disable UI elements or show spinner,
    - send POST to `/api/ask.php` with prompt,
    - clear textarea immediately after sending,
    - when response returns, show it in response div, re-enable UI.
  - Errors show a Bootstrap alert in response area.
- Accessibility: keyboard focus, aria-live for response updates.

## Implementation steps (high level)
1. Create `index.php` with Bootstrap 5 and jQuery CDN, markup for textarea, toggle, response area.
2. Create `assets/js/app.js` with logic:
   - read debounce ms from server or default,
   - handle toggle state and persist in `localStorage`,
   - implement debounce and send using `fetch` to `/api/ask.php`.
3. Create `api/ask.php`:
   - read `.env` (simple parser),
   - validate provider and config,
   - forward request to provider with correct headers and body,
   - stream the provider response to the client.
4. Add `.env.example` and `README.md`.
5. Manual QA and iterate.

## Quick run instructions (dev)
1. Copy `.env.example` to `.env` and fill values.
2. From project root run the PHP built-in server:

```bash
php -S localhost:8000
```

3. Open `http://localhost:8000/` in your browser. Use Windows voice input to speak into the textarea.

## Security & privacy notes
- Keep keys in `.env` only.
- If deploying publicly, protect `api/ask.php` behind auth and rate limits.
- Sanitize and validate inputs to avoid injection into downstream API parameters.

## Testing and QA
- Test sending a sample prompt and verify response shown.
- Test toggle OFF (no calls sent).
- Test network failure and provider 4xx/5xx responses — ensure user sees error.
- Test with Ollama local server if using Ollama.

## Next steps & optional enhancements
- Persist conversation history server-side in a more robust way (e.g., SQLite).
- Add user-selectable models and providers via the UI.
- Add unit tests for the PHP backend.

## Estimated minimal timeline
- Plan & scaffold: 30–60 minutes.
- Backend proxy: 45–90 minutes (depends on provider API details).
- Client JS & polish: 30–60 minutes.

---

Created by request in workspace `interview`. Follow the file map above next to scaffold the project. 
