# Interview Assistant

A simple web application that acts as an interview assistant. It takes spoken or typed input, sends it to a large language model (LLM) like Ollama or OpenAI, and displays the response. This tool is designed to be used with a voice-to-text utility like Windows 11 Voice Assistance.

## Features

*   **Voice/Text Input:** Accepts user input in a textarea.
*   **Automatic Submission:** Automatically sends the input to an LLM after a short delay (debounced).
*   **LLM Integration:** Supports both Ollama and OpenAI as backends.
*   **Enable/Disable Toggle:** A toggle switch to control automatic submission.
*   **Responsive UI:** Clean and simple interface built with Bootstrap 5.

## Requirements

*   PHP 7.4 or higher.
*   An API key for either Ollama or OpenAI.

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
    php -S localhost:8000 -t .
    ```

4.  **Open the application:**
    Open your web browser and navigate to `http://localhost:8000/`.

## Configuration

The application is configured through the `.env` file. The following variables are available:

*   `PROVIDER`: The LLM provider to use. Can be `ollama` or `openai`.
*   `OLLAMA_URL`: The URL for your Ollama instance (if using Ollama).
*   `OLLAMA_MODEL`: The Ollama model to use.
*   `OPENAI_API_KEY`: Your OpenAI API key (if using OpenAI).
*   `OPENAI_BASE_URL`: The base URL for the OpenAI API (optional).
*   `DEBOUNCE_MS`: The debounce delay in milliseconds before sending the input.
*   `TIMEOUT_SECONDS`: The request timeout in seconds.

**Note:** Do not commit the `.env` file to version control.

## API

The application uses a simple PHP proxy to communicate with the LLM provider.

*   **Endpoint:** `POST /api/ask.php`
*   **Request Body (JSON):**
    ```json
    {
      "prompt": "Your input text..."
    }
    ```
*   **Response (JSON):**
    ```json
    {
      "success": true,
      "response": "The LLM's response."
    }
    ```
