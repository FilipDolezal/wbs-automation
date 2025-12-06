# Task Uploader Module

This module is responsible for parsing tasks from an Excel WBS (Work Breakdown Structure) file and uploading them to Redmine.

## Configuration

The primary configuration file for this module is located at:
`src/TaskUploader/Config/parameters.yaml`

This file controls the connection to Redmine, default values for new issues, and how the Excel file is interpreted.

### 1. Redmine Connection

Configure your Redmine instance URL and API key.

```yaml
parameters:
    redmine.url: 'http://redmine:3000'
    redmine.api_key: 'your-api-key-here'
```

*   **redmine.url**: The base URL of your Redmine installation.
*   **redmine.api_key**: Your personal API access key (found in "My Account" on Redmine).

### 2. Default Task Settings

These settings define the default attributes for new tasks created in Redmine if specific options aren't provided via the CLI arguments.

```yaml
    redmine.default.tracker: 'Požadavek' # e.g., 'Feature', 'Bug', 'Task'
    redmine.default.status: 'Nový'       # e.g., 'New', 'In Progress'
    redmine.default.priority: 'Default'  # e.g., 'Normal', 'High'
```

### 3. WBS Excel Configuration

This section tells the parser how to read your Excel file.

#### Spreadsheet Settings

```yaml
    wbs.spreadsheet_name: 'WBS - vývoj' # The name of the sheet tab to read
```

#### Column Identifiers (Mandatory)

You must map specific logical roles to Excel columns. These are required for the hierarchy (Initiative -> Epic -> Task) to work correctly.

```yaml
    wbs.column_identifiers:
        taskName: 'A'   # Column containing the Task Subject
        initiative: 'B' # Column containing the Initiative name (Parent level 1)
        epic: 'C'       # Column containing the Epic name (Parent level 2)
        redmineId: 'D'  # Column to store/read the Redmine ID (if updating/referencing)
```

#### Column Definitions

This section defines the schema for your Excel columns. You can map columns to standard Redmine fields or custom fields.

**Structure:**

```yaml
    wbs.column_definition:
        <Column_Letter>:
            type: <string|int|float>
            nullable: <true|false> (default: true)
            calculated: <true|false> (default: false)
          # ONE OF THE FOLLOWING:
            field: <redmine_standard_field>
            custom_field: <redmine_custom_field_name>
```

**Available Standard Fields (`field`):**
*   `subject` (Title of the task)
*   `description` (Description/Body of the task)
*   `estimatedHours` (Estimated time)
*   `startDate`
*   `dueDate`

**Custom Fields (`custom_field`):**
Use the exact name of the Custom Field as it appears in Redmine.

**Example:**

```yaml
    wbs.column_definition:
        A:
          type: string
          nullable: false
          field: subject  # Maps Column A to the Task Subject

        F:
          type: float
          custom_field: 'Odhad pro programátora' # Maps Column F to Redmine Custom Field "Odhad pro programátora"

        K:
          type: float
          calculated: true
          field: estimatedHours # Maps Column K to Estimated Hours (marked as calculated in Excel)

        L:
          type: string
          field: description # Maps Column L to the Task Description
```
