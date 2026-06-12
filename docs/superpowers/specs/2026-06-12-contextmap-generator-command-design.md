# Context Map Generator — Symfony CLI Command Design

**Date:** 2026-06-12
**Branch:** feat/contextmap-generator-command
**Status:** approved

## Goal

Porter le générateur de context map (initialement des scripts standalone `bin/`) en commandes Symfony CLI injectables et testables, en déplaçant les classes tool dans `src/Shared/Infrastructure/ContextMap/` et en ajoutant un fichier de configuration optionnel `contextmap.config.yaml`.

## Architecture

```
src/Shared/
  Infrastructure/
    ContextMap/
      ContractScanner.php          # portage depuis tools/ContextMap/
      DeptracRulesetParser.php
      ContextMapBuilder.php
      MermaidWriter.php
      YamlWriter.php
      ContextMapChecker.php
      ContextMapConfig.php         # value object
      ContextMapConfigLoader.php   # lit contextmap.config.yaml, merge avec defaults
  UI/
    Console/
      GenerateContextMapCommand.php   # app:contextmap:generate
      CheckContextMapCommand.php      # app:contextmap:check

contextmap.config.yaml              # optionnel, à la racine du projet
contextmap.yaml                     # généré
docs/context-map.md                 # généré (Mermaid)
```

**Supprimés :**
- `bin/generate-contextmap.php`
- `bin/check-contextmap.php`
- `tools/ContextMap/` (répertoire entier)

## Configuration

`contextmap.config.yaml` est optionnel. S'il est absent, les valeurs par défaut s'appliquent. S'il est présent, il est mergé avec les defaults.

```yaml
# contextmap.config.yaml
contextmap:
  src_dir: src/
  deptrac_file: deptrac-contexts.yaml
  output_yaml: contextmap.yaml
  output_mermaid: docs/context-map.md
  excluded_layers:
    - Shared
    - Vendor
    - Payment
    - Search
    - Translation
```

`excluded_layers` : layers ignorés lors du parsing du ruleset deptrac (layers non-bounded-context).

**Priorité des valeurs :** option CLI > `contextmap.config.yaml` > valeurs par défaut codées.

`ContextMapConfigLoader` cherche `contextmap.config.yaml` à `$projectDir/contextmap.config.yaml`. Si le fichier est absent, retourne un `ContextMapConfig` avec tous les defaults. Si présent, merge les clés définies sur les defaults.

## Commandes

### `app:contextmap:generate`

```
php bin/console app:contextmap:generate [--output-yaml=<path>] [--output-mermaid=<path>]
```

**Injection :** `ContractScanner`, `DeptracRulesetParser`, `ContextMapBuilder`, `YamlWriter`, `MermaidWriter`, `ContextMapConfigLoader`, `string $projectDir`

**Séquence :**
1. Charger la config (`ContextMapConfigLoader`)
2. Vérifier que `deptrac_file` existe — sinon `<error>` + `FAILURE`
3. Vérifier que `src_dir` existe — sinon `<error>` + `FAILURE`
4. `ContractScanner::scan($srcDir)`
5. `DeptracRulesetParser::parse($deptracFile, $excludedLayers)`
6. `ContextMapBuilder::build($contracts, $consumes)`
7. `YamlWriter::write($map, $outputYaml)`
8. `MermaidWriter::write($map, $outputMermaid)`
9. `<info>Generated {yaml} and {mermaid}</info>` + `SUCCESS`

### `app:contextmap:check`

```
php bin/console app:contextmap:check
```

**Injection :** `ContextMapChecker`, `ContextMapConfigLoader`, `string $projectDir`

**Séquence :**
1. Charger la config
2. Vérifier que `contextmap.yaml` existe — sinon `<error>Run app:contextmap:generate first</error>` + `FAILURE`
3. `ContextMapChecker::check($contextMapPath, $srcDir)`
4. Afficher chaque `[OK]` en `<info>`, chaque `[FAIL]` en `<error>`
5. `SUCCESS` si aucun fail, `FAILURE` sinon

## Injection de dépendances

Autowiring standard via `config/services/shared.yaml` (le répertoire `src/Shared/` est déjà scanné).

`$projectDir` lié via `bind` dans `config/services/shared.yaml` :

```yaml
services:
  _defaults:
    bind:
      string $projectDir: '%kernel.project_dir%'
```

`DeptracRulesetParser` reçoit les `excluded_layers` via la config (plus hardcodés dans la classe).

## Deptrac

Ajouter `App\Shared\Infrastructure\ContextMap` comme sous-couche de `Shared` dans `deptrac.yaml` pour autoriser la dépendance sur `Vendor` (`symfony/yaml`, fonctions filesystem).

## Makefile

Remplacer les cibles existantes :

```makefile
contextmap: ## Generate contextmap.yaml and docs/context-map.md
	$(DOCKER_COMPOSE) exec php bin/console app:contextmap:generate

check-contextmap: ## Validate contextmap against source
	$(DOCKER_COMPOSE) exec php bin/console app:contextmap:check
```

## Tests

### Tests portés (namespace mis à jour uniquement)

Tous les tests de `tests/ContextMap/` gardent leur logique — seul le namespace `Tools\ContextMap` → `App\Shared\Infrastructure\ContextMap` change. Restent en `#[Group('unit')]`.

- `ContextMapBuilderTest`
- `ContextMapCheckerTest`
- `ContractScannerTest`
- `DeptracRulesetParserTest`
- `MermaidWriterTest`
- `YamlWriterTest`

### Nouveaux tests

| Classe | Groupe | Ce qui est vérifié |
|---|---|---|
| `ContextMapConfigLoaderTest` | unit | merge avec defaults, fichier absent, fichier partiel |
| `GenerateContextMapCommandTest` | functional | retourne `SUCCESS`, fichiers écrits |
| `CheckContextMapCommandTest` | functional | `SUCCESS` sur contextmap valide, `FAILURE` si classes manquantes, `FAILURE` si contextmap absent |

## Gestion d'erreurs

| Situation | Comportement |
|---|---|
| `deptrac-contexts.yaml` absent | `<error>deptrac-contexts.yaml not found</error>` + `FAILURE` |
| `src_dir` introuvable | `<error>src directory not found: {path}</error>` + `FAILURE` |
| `contextmap.yaml` absent (check) | `<error>contextmap.yaml not found. Run: app:contextmap:generate</error>` + `FAILURE` |
