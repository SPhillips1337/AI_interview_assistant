# Interview Assistant

A simple web application that acts as an interview assistant. It takes spoken or typed input, sends it to a large language model (LLM) like Ollama or OpenAI, and displays the response. The main page displays a scrollable chat history, allowing you to see the full context of your conversation. This tool is designed to be used with a voice-to-text utility like Windows 11 Voice Assistance.

## Features

*   **Voice/Text Input:** Accepts user input in a textarea.
*   **Automatic Submission:** Automatically sends the input to an LLM after a short delay (debounced).
*   **LLM Integration:** Supports both Ollama and OpenAI as backends.
*   **Quick Response:** Displays a quick, one-paragraph summary of the user's query topic using a lightweight model, appearing before the main response.
*   **Markdown Response Rendering:** Assistant responses are rendered with full markdown support for improved readability, including headers, lists, code blocks, bold/italic text, and links.
*   **Environment Configuration Integration:** Proper loading and integration of PHP environment variables with JavaScript configuration.
*   **Enable/Disable Toggle:** A toggle switch to control automatic submission.
*   **Responsive UI:** Clean and simple interface built with Bootstrap 5.
*   **Conversation History:** View a timeline of all your conversations on a separate history page.
*   **Dig Deeper:** Further research a response using Perplexica.
*   **Conversational Memory:** Remembers the context of your recent conversation, allowing you to ask follow-up questions naturally.
*   **Topic Recognition:** Automatically identifies the main topic of your conversation, which you can view on the history page.
*   **Chat Interface:** Displays a scrollable history of your conversation on the main page.
*   **Conversation Report:** Generate a downloadable HTML report of your conversation, including a summary, keywords, and the full transcript.
*   **Topic Analysis:** The history page now features a dynamic word cloud that visualizes the most frequent and recent topics in your conversation.
*   **Streaming LLM Responses:** LLM responses are streamed to the user in real-time, improving perceived performance.
*   **Responsive Navbar:** A responsive navigation bar has been added for easy navigation between the main pages.

## Requirements

*   PHP 7.4 or higher with support for reading .env files.
*   An API key for either Ollama or OpenAI.
*   Web browser with JavaScript enabled.
*   Internet connection for CDN resources (Bootstrap, jQuery, Marked.js).

## Installation and Running

1.  **Clone the repository:**
    ```bash
    git clone <repository-url>
    cd interview-assistant
    ```

2.  **Configure environment variables:**
    Copy the `.env.example` file to a new file named `.env`.
    ```bash
    cp .env.example .env
    ```
    Open `.env` and fill in the required values for your chosen LLM provider (Ollama or OpenAI).

3.  **Start the PHP built-in server:**
    From the project root directory, run the following command:
    ```bash
    php -S localhost:8000
    ```

4.  **Open the application:**
    Open your web browser and navigate to `http://localhost:8000/`.

## History Page

To view the conversation details, open `http://localhost:8000/history.php` in your browser. This page contains two tabs:

*   **History:** A timeline of your conversation that updates in near real-time.
*   **Current Topic:** Displays the main topic of the conversation, analyzed by an LLM. This also updates as the conversation progresses.

If you have configured the `PERPLEXICA_URL` in your `.env` file, you will see a "Dig Deeper" button next to each response, which allows you to send that response to your Perplexica instance for further research.

You can also download a full report of the conversation by clicking the "Download Report" button.

## Configuration

The application is configured through the `.env` file. The following variables are available:

*   `PROVIDER`: The LLM provider to use. Can be `ollama` or `openai`.
*   `OLLAMA_URL`: The URL for your Ollama instance (if using Ollama).
*   `OLLAMA_MODEL`: The Ollama model to use.
*   `OLLAMA_QUICK_MODEL`: The lightweight Ollama model to use for the quick response feature.
*   `OPENAI_API_KEY`: Your OpenAI API key (if using OpenAI).
*   `OPENAI_BASE_URL`: The base URL for the OpenAI API (optional).
*   `DEBOUNCE_MS`: The debounce delay in milliseconds before sending the input.
*   `TIMEOUT_SECONDS`: The request timeout in seconds.
*   `PERPLEXICA_URL`: The URL for your Perplexica instance (e.g., `http://192.168.5.227:3030`).

**Note:** Do not commit the `.env` file to version control.

## API

The application uses a simple PHP proxy to communicate with the LLM provider.

*   **Endpoint:** `POST /api/ask.php`
*   **Request Body (JSON):**
    ```json
    {
      "conversation": [
        { "role": "user", "content": "First question..." },
        { "role": "assistant", "content": "First answer..." },
        { "role": "user", "content": "Follow-up question..." }
      ]
    }
    ```
*   **Response (Server-Sent Events):**
    The endpoint streams the response using SSE. Each message is a JSON object with the following structure:
    ```json
    data: {"success":true,"response":"The LLM's response chunk."}
    ```

*   **Endpoint:** `POST /api/quick_response.php`
*   **Request Body (JSON):**
    ```json
    {
      "prompt": "User's query..."
    }
    ```
*   **Response (JSON):**
    ```json
    {
      "success": true,
      "response": "A short, one-paragraph summary of the topic."
    }
    ```

*   **Endpoint:** `GET /api/history.php`
*   **Response (JSON):**
    Returns the conversation history as a JSON array.

*   **Endpoint:** `GET /api/topic.php`
*   **Response (JSON):**
    ```json
    {
      "topic": "The identified topic of the conversation."
    }
    ```

*   **Endpoint:** `GET /api/topics.php`
*   **Response (JSON):**
    An array of topics and their calculated weights, suitable for a word cloud.
    ```json
    [
      [ "topic1", 10.5 ],
      [ "topic2", 8.0 ],
      [ "topic3", 5.5 ]
    ]
    ```

*   **Endpoint:** `GET /api/generate_report.php`
*   **Response:**
    An HTML page attachment containing a full report of the conversation.

*   **Endpoint:** `POST /api/perplexica.php`
*   **Request Body (JSON):**
    ```json
    {
      "query": "Text to research further..."
    }
    ```
*   **Response (JSON):**
    Proxies the request to the configured Perplexica instance.