# Meralco Kuryente Load Tracker

A lightweight, privacy-focused PHP web application designed to help Meralco Kuryente Load (prepaid electricity) users track their daily consumption, monitor rates, and visualize spending habits.

![Version](https://img.shields.io/badge/version-1.2.0-orange)
![License](https://img.shields.io/badge/license-MIT-green)

## ✨ Features

- **Daily Consumption Tracking:** Log your kWh remaining, current rate, and balance every day.
- **Automated Cost Calculation:** Automatically computes your daily spend based on the difference in your remaining balance.
- **Smart Data Merge:** Handles same-day rate adjustments or multiple readings by merging them into a single record without losing data.
- **AI-Powered Data Entry:** Includes a built-in prompt for **Google Gemini** to extract billing data from your screenshots or SMS notifications.
- **Monthly & Yearly Navigation:** Organizes your data into yearly JSON files with easy monthly filtering.
- **Mobile Responsive:** Modern, clean UI that works perfectly on both desktop and mobile devices.
- **Privacy First:** All data is stored locally in `.json` files on your server. No database or cloud account required.

## 🚀 Getting Started

### Prerequisites

- A web server with **PHP 7.4+** installed.
- Write permissions for the project directory (to save JSON data).

### Installation

1. Clone the repository:
   ```bash
   git clone https://github.com/your-username/kload-tracker.git
   ```
2. Move the files to your web server's public directory (e.g., `www`, `htdocs`).
3. Open the application in your browser:
   ```
   http://localhost/kload-tracker/index.php
   ```

## 🤖 AI Assistant Workflow

The application is designed to make data entry effortless using AI:

1. **Copy Prompt:** Click the "Copy Prompt" button in the **Import Data** section.
2. **Consult Gemini:** Paste the prompt into [Google Gemini](https://gemini.google.com/) and upload a screenshot of your Meralco SMS or App dashboard.
3. **Paste & Import:** Use the "📋 Paste from Clipboard" button to put the AI-generated JSON into the tracker and click **Import Backup**.

## 📂 Project Structure

- `index.php`: The main application logic and UI.
- `YYYY.json`: Data files automatically generated for each year (e.g., `2024.json`, `2025.json`).
- `.gitignore`: Configured to exclude specific system files while keeping data structures.

## 🛠️ Built With

- **PHP** - Backend logic and data processing.
- **Vanilla CSS** - Modern styling and animations.
- **JavaScript** - Modal handling and clipboard interactions.
- **JSON** - Lightweight local data storage.

## 🤝 Contributing

Contributions are welcome! Feel free to open an issue or submit a pull request for any features or bug fixes.

## 📄 License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

---
*Disclaimer: This project is not affiliated with Meralco (Manila Electric Company).*
