# Migracja storage do named volume

Skrypt `migrate_storage_volume.py` przenosi pliki z `/app/backend/storage` wewnątrz kontenera
do named volume `all-in-one_storage`, co zapewnia trwałość danych między aktualizacjami.

## Wymagania

- Python 3.10+
- Docker + Docker Compose
- Uprawnienia do uruchamiania `docker` (sudo lub grupa docker)

## Uruchomienie lokalne (development)

```bash
cd docker/all-in-one
python3 migrate_storage_volume.py
```

## Wdrożenie na serwer produkcyjny

```bash
# 1. Zaloguj się na serwer
ssh user@serwer

# 2. Przejdź do katalogu projektu
cd /ścieżka/do/hi.events

# 3. Pobierz najnowsze zmiany z aktualnego brancha
git pull

# 4. Uruchom skrypt migracji
cd docker/all-in-one
python3 migrate_storage_volume.py
```

Skrypt zapyta o dwie rzeczy:

```
Zatrzymać kontenery? (docker compose down) [t/N]: t
Przebudować obraz przed startem? (--no-cache, zalecane przy nowym kodzie) [t/N]: t
```

Odpowiedz **t** na oba pytania — przy nowym kodzie zawsze przebuduj obraz.

## Co robi skrypt

Skrypt przeprowadzi Cię przez 6 kroków:

| Krok | Opis |
|------|------|
| 1 | Auto-wykrywa działający kontener (`all-in-one`) |
| 2 | Robi backup storage → `storage_backup_YYYYMMDD_HHMMSS/` w bieżącym katalogu |
| 3 | Pyta o potwierdzenie i zatrzymuje kontenery (`docker compose down`) |
| 4 | Pyta czy przebudować obraz (`--no-cache`) — wybierz **t** przy nowym kodzie |
| 5 | Uruchamia kontenery (`docker compose up -d`) i kopiuje backup do wolumenu |
| 6 | Weryfikuje zawartość wolumenu |

Skrypt **zawsze pyta przed akcjami destruktywnymi** (zatrzymanie kontenerów, przebudowa obrazu).

## Kiedy wybrać "przebuduj obraz"

```
Przebudować obraz przed startem? (--no-cache, zalecane przy nowym kodzie) [t/N]:
```

- **`t`** — gdy wdrażasz nową wersję kodu (zmienił się `Dockerfile.all-in-one` lub kod aplikacji)
- **`N`** — gdy tylko migrujesz storage, kod się nie zmienił

## Po zakończeniu

Skrypt wypisze ścieżkę do backupu. Po weryfikacji że aplikacja działa poprawnie możesz go usunąć:

```bash
rm -rf docker/all-in-one/storage_backup_*
```

## Weryfikacja ręczna

```bash
# Sprawdź czy kontener działa
docker ps --format "{{.Names}}"

# Sprawdź zawartość storage w kontenerze
docker exec all-in-one-all-in-one-1 ls -la /app/backend/storage/app/public/

# Sprawdź wolumen
docker volume inspect all-in-one_storage
```

## Rozwiązywanie problemów

**Kontener nie startuje po migracji:**
```bash
docker compose -f docker/all-in-one/docker-compose.yml logs --tail=50
```

**Brak uprawnień do docker:**
```bash
sudo python3 migrate_storage_volume.py
# lub dodaj użytkownika do grupy docker:
sudo usermod -aG docker $USER
```

**Wolumen ma złą nazwę:**
Nazwa wolumenu to `<katalog_z_compose>_storage`. Jeśli `docker-compose.yml` leży w katalogu
`all-in-one`, wolumen nazywa się `all-in-one_storage`. Sprawdź:
```bash
docker volume ls
```
