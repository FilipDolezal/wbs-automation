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

This section tells the parser how to read your Excel file. You can define multiple worksheet configurations to support different WBS structures within the same application.

**Structure:**

```yaml
    wbs.worksheet_definitions:
        -
            name: 'Sheet Name' # The name of the Excel sheet tab to read
            
            # Mandatory: Map logical roles to Excel columns
            column_identifiers:
                taskName: 'A'   # Column containing the Task Subject
                initiative: 'B' # Column containing the Initiative name (Parent level 1)
                epic: 'C'       # Column containing the Epic name (Parent level 2)
                redmineId: 'D'  # Column to store/read the Redmine ID
                estimatedHours: 'K' # (Optional) Column for estimated hours
            
            # Define schema for specific columns
            column_definition:
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
    wbs.worksheet_definitions:
        -
            name: 'WBS - vývoj'
            
            column_identifiers:
                taskName: 'A'
                initiative: 'B'
                epic: 'C'
                redmineId: 'D'
                estimatedHours: 'K'
            
            column_definition:
                A:
                    type: string
                    nullable: false
                    field: subject
                B:
                    type: string
                C:
                    type: string
                D:
                    type: int
                F:
                    type: float
                    custom_field: 'Odhad pro programátora'
                K:
                    type: float
                    calculated: true
                    field: estimatedHours
                L:
                    type: string
                    field: description
                N:
                    type: string
                    custom_field: 'Odkaz na specifikaci'
```
