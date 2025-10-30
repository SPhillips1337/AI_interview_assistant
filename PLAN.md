# Interview Assistant — Project Plan

## One-line summary
A small local HTML/PHP/jQuery site (Bootstrap 5) that accepts spoken or typed input in a textarea, then—after a short debounce—sends it to an LLM (Ollama or OpenAI) via a simple PHP proxy, clears the input, and shows the LLM response in a response pane. Includes an on/off toggle to enable/disable automatic sending.

## Goals and acceptance criteria
- UI: Clean, responsive page using Bootstrap 5 with a textarea, response area, and toggle button.
- Behavior: When the toggle is ON, user input is auto-sent a short while after typing (debounced). The textarea is cleared on send. Response is shown below the input.
- Backend: PHP endpoint that reads configuration from `.env` and forwards requests to either Ollama (local or remote) or OpenAI.
- Security: API keys live only in `.env` (never committed).
- Developer experience: Can run locally via PHP built-in server. Minimal dependencies (Bootstrap/jQuery via CDN).

## Assumptions
- The user will use Windows 11 voice assist to input text into the textarea (client-side). The app does not perform speech recognition — Windows handles that.
- LLM calls are proxied through PHP to avoid exposing API keys in the browser and to avoid CORS problems.
- Ollama may be hosted at a local HTTP endpoint (e.g., `http://127.0.0.1:11434`) or a remote Ollama-compatible endpoint. OpenAI will use their HTTP API.
- `shortwhile` debounce will be implemented as a configurable delay (default ~700ms).

## Contract (tiny)
- Input: user text string from textarea.
- Output: JSON object with at least { success: bool, response: string, error?: string }.
- Error modes: network timeouts, provider error, invalid config.

## Edge cases to handle
- Empty input (do nothing).
- Very long input (truncate or return helpful message / let backend handle length limits).
- Provider down / 401 / 4xx / 5xx errors — report to client.
- Toggle OFF: no requests are sent.
- Rapid input: debounce prevents flooding provider.

## Architecture / File map
- `index.php` — main page, includes Bootstrap + jQuery via CDN, minimal markup, toggle and textarea.
- `api/ask.php` — PHP proxy; reads `.env`, chooses provider, forwards request, returns JSON.
- `assets/js/app.js` — client JS: debounce, toggle handling, fetch to `/api/ask.php`, UI updates.
- `assets/css/style.css` — small styles.
- `.env.example` — example env variables.
- `README.md` — run instructions and notes.

## Environment variables (`.env` / `.env.example`)
- PROVIDER=ollama|openai
- OLLAMA_URL=http://127.0.0.1:11434
- OLLAMA_MODEL=ggml-gpt4o (or your model)
- OPENAI_API_KEY=sk-...
- OPENAI_BASE_URL=https://api.openai.com (optional; defaults to OpenAI official API)
- DEBOUNCE_MS=700
- TIMEOUT_SECONDS=30

Note: Do not commit `.env`.

## API behavior (proxy)
- Endpoint: `POST /api/ask.php`
- Request body (JSON): { "prompt": "..." }
- Response (JSON): { "success": true, "response": "LLM answer text" }

Provider implementation notes:
- Ollama: POST to `${OLLAMA_URL}/api/generate` with { model, input }. (Check Ollama API spec — adapt payload to your Ollama version.)
- OpenAI: POST to `${OPENAI_BASE_URL}/v1/chat/completions` with messages format or to `/v1/responses` depending on your target API, include Authorization header `Bearer ${OPENAI_API_KEY}`.
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
   - implement debounce and send using `$.ajax` to `/api/ask.php`.
3. Create `api/ask.php`:
   - read `.env` (simple parser),
   - validate provider and config,
   - forward request to provider with correct headers and body,
   - normalize provider response to a simple string and return JSON.
4. Add `.env.example` and `README.md`.
5. Manual QA and iterate.

## Quick run instructions (dev)
1. Copy `.env.example` to `.env` and fill values.
2. From project root run the PHP built-in server:

```bash
php -S localhost:8000 -t .
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
- Persist conversation history client-side or server-side.
- Add user-selectable model and provider via UI.
- Add streaming responses (server-sent events / chunked responses) for lower latency.
- Add unit tests for PHP proxy (mock provider responses).

## Estimated minimal timeline
- Plan & scaffold: 30–60 minutes.
- Backend proxy: 45–90 minutes (depends on provider API details).
- Client JS & polish: 30–60 minutes.

---

Created by request in workspace `interview`. Follow the file map above next to scaffold the project. 
