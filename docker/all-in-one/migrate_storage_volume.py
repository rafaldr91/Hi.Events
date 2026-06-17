#!/usr/bin/env python3
"""
Skrypt migracji storage do named volume dla hi.events all-in-one.

Wykonuje kroki:
  1. Wykrywa działający kontener
  2. Robi backup storage z kontenera na hosta
  3. Zatrzymuje i usuwa kontenery (docker compose down)
  4. Startuje z nowym wolumenem (docker compose up -d)
  5. Kopiuje backup do nowego wolumenu
  6. Weryfikuje migrację
"""

import subprocess
import sys
import os
import shutil
from pathlib import Path
from datetime import datetime

# --- konfiguracja ---
COMPOSE_DIR = Path(__file__).parent.resolve()
COMPOSE_FILE = COMPOSE_DIR / "docker-compose.yml"
BACKUP_DIR = COMPOSE_DIR / f"storage_backup_{datetime.now().strftime('%Y%m%d_%H%M%S')}"
CONTAINER_STORAGE_PATH = "/app/backend/storage"
VOLUME_NAME = "all-in-one_storage"   # <katalog>_<nazwa_wolumenu_w_compose>
SERVICE_NAME = "all-in-one"


def run(cmd: list[str], check: bool = True, capture: bool = False) -> subprocess.CompletedProcess:
    print(f"  $ {' '.join(cmd)}")
    return subprocess.run(
        cmd,
        check=check,
        capture_output=capture,
        text=True,
        cwd=COMPOSE_DIR,
    )


def run_output(cmd: list[str]) -> str:
    result = run(cmd, check=True, capture=True)
    return result.stdout.strip()


def confirm(prompt: str) -> bool:
    answer = input(f"\n{prompt} [t/N]: ").strip().lower()
    return answer in ("t", "tak", "y", "yes")


def step(n: int, title: str):
    print(f"\n{'='*60}")
    print(f"  Krok {n}: {title}")
    print(f"{'='*60}")


def find_container() -> str | None:
    try:
        names = run_output(["docker", "ps", "--format", "{{.Names}}"])
        for name in names.splitlines():
            if "all-in-one" in name:
                return name
    except subprocess.CalledProcessError:
        pass
    return None


def volume_exists(volume: str) -> bool:
    try:
        result = run_output(["docker", "volume", "ls", "--format", "{{.Name}}"])
        return volume in result.splitlines()
    except subprocess.CalledProcessError:
        return False


def main():
    print("\nhi.events — Migracja storage do named volume")
    print("=" * 60)
    print(f"  Katalog compose : {COMPOSE_DIR}")
    print(f"  Plik compose    : {COMPOSE_FILE}")
    print(f"  Wolumen docelowy: {VOLUME_NAME}")
    print(f"  Backup dir      : {BACKUP_DIR}")

    if not COMPOSE_FILE.exists():
        print(f"\nBŁĄD: Nie znaleziono {COMPOSE_FILE}")
        sys.exit(1)

    # ------------------------------------------------------------------ krok 1
    step(1, "Wykrywanie działającego kontenera")

    container = find_container()
    if container:
        print(f"  Znaleziono kontener: {container}")
    else:
        print("  Nie znaleziono działającego kontenera all-in-one.")
        print("  (Backup zostanie pominięty — kontener nie jest uruchomiony)")

    # ------------------------------------------------------------------ krok 2
    step(2, "Backup storage z kontenera na hosta")

    if container:
        if volume_exists(VOLUME_NAME):
            print(f"  Wolumen '{VOLUME_NAME}' już istnieje — dane są w wolumenie.")
            print(f"  Backup zostanie skopiowany z wolumenu zamiast z kontenera.")
            os.makedirs(BACKUP_DIR, exist_ok=True)
            run([
                "docker", "run", "--rm",
                "-v", f"{VOLUME_NAME}:/source",
                "-v", f"{BACKUP_DIR}:/dest",
                "alpine", "sh", "-c", "cp -r /source/. /dest/",
            ])
        else:
            print(f"  Kopiuję {CONTAINER_STORAGE_PATH} → {BACKUP_DIR}")
            run(["docker", "cp", f"{container}:{CONTAINER_STORAGE_PATH}", str(BACKUP_DIR)])

        backup_size = run_output(["du", "-sh", str(BACKUP_DIR)])
        print(f"  Backup gotowy: {backup_size}")
    else:
        print("  Pomijam backup — kontener nie działa.")
        if not confirm("Kontynuować bez backupu?"):
            print("Przerwano.")
            sys.exit(0)

    # ------------------------------------------------------------------ krok 3
    step(3, "Zatrzymanie i usunięcie kontenerów")

    if not confirm("Zatrzymać kontenery? (docker compose down)"):
        print("Przerwano.")
        sys.exit(0)

    run(["docker", "compose", "-f", str(COMPOSE_FILE), "down"])
    print("  Kontenery zatrzymane.")

    # ------------------------------------------------------------------ krok 4
    step(4, "Przebudowa obrazu i uruchomienie z nowym wolumenem")

    rebuild = confirm("Przebudować obraz przed startem? (--no-cache, zalecane przy nowym kodzie)")
    if rebuild:
        print("  Buduję obraz (to może potrwać kilka minut)...")
        run(["docker", "compose", "-f", str(COMPOSE_FILE), "build", "--no-cache"])
        print("  Obraz zbudowany.")

    run(["docker", "compose", "-f", str(COMPOSE_FILE), "up", "-d"])
    print("  Kontenery uruchomione.")

    # Poczekaj aż kontener wstanie
    import time
    print("  Czekam 5s na start kontenerów...")
    time.sleep(5)

    new_container = find_container()
    if not new_container:
        print("  OSTRZEŻENIE: Nie znaleziono działającego kontenera po starcie.")
        print("  Sprawdź: docker compose logs")

    # ------------------------------------------------------------------ krok 5
    step(5, "Kopiowanie danych z backupu do wolumenu")

    if container and BACKUP_DIR.exists():
        print(f"  Kopiuję {BACKUP_DIR} → wolumen {VOLUME_NAME}")

        # Sprawdź czy backup zawiera podkatalog 'storage'
        backup_source = BACKUP_DIR / "storage"
        if backup_source.exists():
            source_path = str(backup_source)
        else:
            source_path = str(BACKUP_DIR)

        run([
            "docker", "run", "--rm",
            "-v", f"{source_path}:/source",
            "-v", f"{VOLUME_NAME}:/dest",
            "alpine", "sh", "-c", "cp -r /source/. /dest/",
        ])
        print("  Dane skopiowane do wolumenu.")

        target_container = find_container()
        if target_container:
            print("  Ustawiam uprawnienia przez kontener (jako root)...")
            run([
                "docker", "exec", "-u", "root", target_container,
                "chown", "-R", "www-data:www-data", CONTAINER_STORAGE_PATH,
            ])
    else:
        print("  Pomijam — brak backupu lub kontener nie działał przed migracją.")

    # ------------------------------------------------------------------ krok 6
    step(6, "Weryfikacja")

    target_container = find_container()
    if target_container:
        print(f"  Sprawdzam zawartość {CONTAINER_STORAGE_PATH}/app/public/ w kontenerze {target_container}:")
        try:
            result = run_output([
                "docker", "exec", target_container,
                "ls", "-la", f"{CONTAINER_STORAGE_PATH}/app/public/",
            ])
            print(result)
        except subprocess.CalledProcessError:
            print("  (Katalog public/ może być pusty lub jeszcze nie istnieć)")

        print(f"\n  Sprawdzam wolumen {VOLUME_NAME}:")
        run(["docker", "volume", "inspect", VOLUME_NAME])
    else:
        print("  BŁĄD: Kontener nie działa. Sprawdź logi: docker compose logs")
        sys.exit(1)

    # ------------------------------------------------------------------ koniec
    print(f"\n{'='*60}")
    print("  MIGRACJA ZAKOŃCZONA")
    print(f"{'='*60}")
    print(f"\n  Backup zapisany w: {BACKUP_DIR}")
    print(f"  Wolumen          : {VOLUME_NAME}")
    print(f"\n  Możesz usunąć backup gdy wszystko działa:")
    print(f"    rm -rf {BACKUP_DIR}")
    print()


if __name__ == "__main__":
    main()
