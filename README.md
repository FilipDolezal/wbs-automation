# PHP Redmine Task Uploader

Tato CLI aplikace automatizuje proces nahrávání úkolů z Excel WBS (Work Breakdown Structure) tabulky do Redmine. Zvládá vztahy nadřazený-podřízený (Iniciativa -> Epic -> Úkol) a zajišťuje, že se úkoly neduplikují.

## Prerekvizity

- [Docker](https://www.docker.com/)
- [Docker Compose](https://docs.docker.com/compose/)

## Průvodce nastavením

### 1. Prostředí Docker

Spusťte aplikaci a lokální instanci Redmine pomocí přiloženého Makefile nebo Docker Compose.

```bash
# Spustit všechny služby
make up
# Spustit lokální instanci Redmine
make up-redmine
```

Tímto se spustí:
- **App**: PHP CLI prostředí.
- **Redmine**: Lokální instance Redmine dostupná na `http://localhost:8088`.
- **Postgres**: Databáze pro Redmine.

### 2. Konfigurace aplikace

Zkopírujte příklad konfiguračního souboru pro vytvoření vaší lokální konfigurace.

```bash
cp src/TaskUploader/Config/parameters.example.yaml src/TaskUploader/Config/parameters.yaml
```

Otevřete `src/TaskUploader/Config/parameters.yaml` a upravte nastavení:

- **redmine.url**: URL vaší instance Redmine (např. `http://redmine:3000` pokud používáte docker síť, nebo `http://host.docker.internal:8088` zevnitř kontejneru).
- **redmine.api_key**: Váš API klíč k Redmine (naleznete v "Můj účet" -> "Zobrazit API klíč" ve vašem profilu Redmine).
- **redmine.default**: Výchozí názvy pro Typ úkolu (Tracker), Stav (Status) a Prioritu. Tyto **musí** v Redmine existovat.

Pro podrobné informace o nastavení parsování Excel souborů a definici sloupců se prosím podívejte do [src/TaskUploader/README.md](src/TaskUploader/README.md).

### 3. Nastavení Redmine (Klíčový krok)

Pokud používáte lokální instanci Redmine (nebo čistou externí instalaci), **musíte** ji před použitím skriptu manuálně nakonfigurovat. Skript spoléhá na to, že v Redmine existují konkrétní Typy úkolů, Stavy, Priority a Vlastní pole.

**Přístup do Redmine:**
- URL: [http://localhost:8088](http://localhost:8088)
- Výchozí přihlašovací údaje: `admin` / `admin`

#### Konfigurace krok za krokem:

1.  **Vytvořte Stavy úkolů (Issue Statuses):**
    - Jděte do **Administrace** -> **Stavy úkolů**.
    - Ujistěte se, že existuje stav s názvem **"New"** (nebo jakýkoliv název, který jste nastavili v `redmine.default.status`).

2.  **Vytvořte Typy úkolů (Trackers):**
    - Jděte do **Administrace** -> **Typy úkolů**.
    - Vytvořte nový typ s názvem **"Bug"** (nebo odpovídající `redmine.default.tracker`).
    - **Důležité:** V záložce "Projekty" v nastavení typu úkolu zaškrtněte projekty, ve kterých chcete tento typ používat (nebo aplikujte na všechny).

3.  **Zkontrolujte Priority:**
    - Jděte do **Administrace** -> **Číselníky**.
    - V sekci "Priority úkolů" se ujistěte, že existuje **"Normal"** (odpovídá `redmine.default.priority`).

4.  **Vytvořte Vlastní pole (Custom Fields) - pokud jsou v Excelu použita:**
    - Příklad konfigurace používá "Estimated Development Hours" a "Link to Specification".
    - Jděte do **Administrace** -> **Vlastní pole**.
    - Klikněte na **"Nové vlastní pole"** -> vyberte **"Úkoly"**.
    - **Název**: Musí přesně odpovídat hodnotě `custom_field` ve vašem `parameters.yaml` (např. `Estimated Development Hours`).
    - **Formát**: Vyberte vhodný formát (např. Číslo pro hodiny, Text/Odkaz pro URL).
    - **Typy úkolů**: Zaškrtněte typy (např. "Bug"), které mají toto pole používat.
    - **Projekty**: Zaškrtněte projekty (nebo "Pro všechny projekty").

5.  **Vytvořte Projekt:**
    - Jděte do **Projekty** -> **Nový projekt**.
    - **Název**: např. "Migrační Projekt".
    - **Identifikátor**: např. `migration-project`. Tento **identifikátor** budete potřebovat pro spuštění příkazu.
    - **Moduly**: Ujistěte se, že je povoleno "Sledování úkolů".
    - **Typy úkolů**: Ujistěte se, že je zaškrtnuto "Bug".

## Použití

Pro spuštění nahrávacího skriptu použijte `make shell` pro spuštění příkazu uvnitř kontejneru `app`.

**Syntaxe:**
```bash
make shell
# Uvnitř kontejneru:
php bin/cli app:upload-tasks [<cesta_k_souboru>] [<nazev_archu>] [<identifikator_projektu>] [možnosti]
```

**Příklad:**
Předpokládejme, že máte Excel soubor `tasks.xlsx` s archem `WBS - vývoj`:

```bash
make shell
# Uvnitř kontejneru:
php bin/cli app:upload-tasks tasks.xlsx "WBS - vývoj" migration-project
```

### Možnosti (Options)

- `-t, --tracker`: Přepsat výchozí typ úkolu (např. `--tracker="Feature"`).
- `-s, --status`: Přepsat výchozí stav (např. `--status="In Progress"`).
- `-p, --priority`: Přepsat výchozí prioritu (např. `--priority="High"`).
- `-of, --output-file`: Cesta k výstupnímu souboru (výchozí: přepíše vstupní soubor).
- `-spe, --skip-parse-error`: Pokračovat ve zpracování, i když některé řádky selžou při parsování (výchozí: zapnuto).
- `-sze, --skip-zero-estimate`: Nenahrávat úkoly s nulovým odhadem času (výchozí: zapnuto).
- `-eth, --existing-task-handler`: Jak naložit s existujícími úkoly nalezenými podle Redmine ID (hodnoty: `skip` (přeskočit), `update` (aktualizovat), `new` (vytvořit nový)). Výchozí: `skip`.

### Zkratky v Makefile

- `make build`: Sestavit docker image.
- `make up`: Spustit kontejnery.
- `make shell`: Vstoupit do shellu kontejneru.