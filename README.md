# PHP Redmine Task Uploader

Tato CLI aplikace automatizuje proces nahrávání úkolů z Excel WBS (Work Breakdown Structure) tabulky do Redmine. Zvládá vztahy nadřazený-podřízený (Iniciativa -> Epic -> Úkol) a zajišťuje, že se úkoly neduplikují.

## Průvodce nastavením

### 1. Prostředí Docker

Aplikace je kontejnerizovaná a předpokládá připojení k externí instanci Redmine.

```bash
# Sestavit obraz
make build

# Spustit kontejner aplikace
make up
```

Tímto se spustí **App**: PHP CLI prostředí, ze kterého budete spouštět nahrávací skript.

### 2. Konfigurace aplikace

Zkopírujte příklad konfiguračního souboru pro vytvoření vaší lokální konfigurace.

```bash
cp src/TaskUploader/Config/parameters.example.yaml src/TaskUploader/Config/parameters.yaml
```

Otevřete `src/TaskUploader/Config/parameters.yaml` a upravte nastavení pro připojení k vašemu externímu Redmine:

- **redmine.url**: URL vaší instance Redmine (např. `https://redmine.vasedomena.cz`).
- **redmine.api_key**: Váš API klíč k Redmine (naleznete v "Můj účet" -> "Zobrazit API klíč" ve vašem profilu Redmine).
- **redmine.default**: Výchozí názvy pro Typ úkolu (Tracker), Stav (Status) a Prioritu. Tyto **musí** v cílovém Redmine existovat.

Pro podrobné informace o nastavení parsování Excel souborů a definici sloupců se prosím podívejte do [src/TaskUploader/README.md](src/TaskUploader/README.md).

## Použití

Pro spuštění nahrávacího skriptu použijte `make bash` pro spuštění příkazu uvnitř kontejneru `app`.

**Syntaxe:**
```bash
make bash
# Uvnitř kontejneru:
php bin/cli app:upload-tasks [<cesta_k_souboru>] [<nazev_archu>] [<identifikator_projektu>] [možnosti]
```

**Příklad:**
Předpokládejme, že máte Excel soubor `tasks.xlsx` s archem `WBS - vývoj`:

```bash
make bash
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
- `make bash`: Vstoupit do shellu kontejneru.