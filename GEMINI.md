# Project: PHP CLI Application

This is a modular PHP CLI application.

## Project Structure

```
/
├── bin/
│   └── cli                # Main entry point for the CLI application
├── config/
│   ├── app.php            # General application configuration
│   └── redmine.php        # Redmine specific configuration (API keys, etc.)
├── src/
│   ├── Common/            # Shared components
│   │   ├── HttpClient/
│   │   │   └── HttpClient.php # Wrapper for a HTTP client library (e.g., Guzzle)
│   │   └── ExcelParser/
│   │       └── ExcelParser.php  # Wrapper for an Excel parsing library
│   ├── TaskUploader/      # The first module
│   │   ├── Command/
│   │   │   └── UploadTasksCommand.php # The command to upload tasks from Excel
│   │   ├── Service/
│   │   │   └── RedmineService.php # Service to interact with the Redmine API
│   │   └── TaskUploaderModule.php # Module definition
│   └── bootstrap.php         # Application bootstrap
├── vendor/                # Composer dependencies
├── composer.json          # Project dependencies
└── GEMINI.md              # Project outline and documentation
```
