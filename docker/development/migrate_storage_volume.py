#!/usr/bin/env python3
"""
Skrypt migracji storage do named volume dla hi.events development.

W dev storage było bind-mountem z backend/storage/ — pliki są już na dysku.
Skrypt kopiuje je do nowego named volume 'development_app-storage'.

Kroki:
  1. Weryfikuje źródło danych (backend/storage/ na dysku)
  2. Zatrzymuje kontenery (docker compose down)
  3. Startuje z nowym wolumenem (docker compose up -d)
  4. Kopiuje dane z dysku do wolumenu
  5. Weryfikuje migrację
"""

import subprocess
import sys
import os
from pathlib import Path
from datetime import datetime

# --- konfiguracja ---
COMPOSE_DIR = Path(__file__).parent.resolve()
COMPOSE_FILE = COMPOSE_DIR / "docker-compose.dev.yml"
REPO_ROOT = COMPOSE_DIR.parent.parent.resolve()
SOURCE_STORAGE = REPO_ROOT / "backend" / "storage"
BACKUP_DIR = COMPOSE_DIR / f"storage_backup_{datetime.now().strftime('%Y%m%d_%H%M%S')}"
CONTAINER_STORAGE_PATH = "/var/www/html/storage"
VOLUME_NAME = "development_app-storage"   # <katalog>_<nazwa_wolumenu_w_compose>
CONTAINER_NAME = "backend"
COMPOSE_FLAGS = ["-f", str(COMPOSE_FILE)]


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


def volume_exists(volume: str) -> bool:
    try:
        result = run_output(["docker", "volume", "ls", "--format", "{{.Name}}"])
        return volume in result.splitlines()
    except subprocess.CalledProcessError:
        return False


def main():
    print("\nhi.events DEV — Migracja storage do named volume")
    print("=" * 60)
    print(f"  Katalog compose  : {COMPOSE_DIR}")
    print(f"  Źródło storage   : {SOURCE_STORAGE}")
    print(f"  Wolumen docelowy : {VOLUME_NAME}")
    print(f"  Backup dir       : {BACKUP_DIR}")

    if not COMPOSE_FILE.exists():
        print(f"\nBŁĄD: Nie znaleziono {COMPOSE_FILE}")
        sys.exit(1)

    # ------------------------------------------------------------------ krok 1
    step(1, "Weryfikacja źródła danych")

    if not SOURCE_STORAGE.exists():
        print(f"  BŁĄD: Nie znaleziono katalogu {SOURCE_STORAGE}")
        sys.exit(1)

    source_size = run_output(["du", "-sh", str(SOURCE_STORAGE)])
    print(f"  Znaleziono storage: {source_size}")

    if volume_exists(VOLUME_NAME):
        print(f"\n  UWAGA: Wolumen '{VOLUME_NAME}' już istnieje.")
        if not confirm("Kontynuować i nadpisać dane w wolumenie?"):
            print("Przerwano.")
            sys.exit(0)

    # ------------------------------------------------------------------ krok 2
    step(2, "Backup storage (kopia z dysku)")

    import shutil
    print(f"  Kopiuję {SOURCE_STORAGE} → {BACKUP_DIR}")
    shutil.copytree(SOURCE_STORAGE, BACKUP_DIR)
    backup_size = run_output(["du", "-sh", str(BACKUP_DIR)])
    print(f"  Backup gotowy: {backup_size}")

    # ------------------------------------------------------------------ krok 3
    step(3, "Zatrzymanie kontenerów")

    if not confirm("Zatrzymać kontenery? (docker compose down)"):
        print("Przerwano.")
        sys.exit(0)

    run(["docker", "compose"] + COMPOSE_FLAGS + ["down"])
    print("  Kontenery zatrzymane.")

    # ------------------------------------------------------------------ krok 4
    step(4, "Uruchomienie z nowym wolumenem")

    run(["docker", "compose"] + COMPOSE_FLAGS + ["up", "-d"])
    print("  Kontenery uruchomione.")

    import time
    print("  Czekam 5s na start kontenerów...")
    time.sleep(5)

    # ------------------------------------------------------------------ krok 5
    step(5, "Kopiowanie danych z dysku do wolumenu")

    print(f"  Kopiuję {SOURCE_STORAGE} → wolumen {VOLUME_NAME}")
    run([
        "docker", "run", "--rm",
        "-v", f"{SOURCE_STORAGE}:/source",
        "-v", f"{VOLUME_NAME}:/dest",
        "alpine", "sh", "-c", "cp -r /source/. /dest/",
    ])
    print("  Dane skopiowane do wolumenu.")
    print("  Ustawiam uprawnienia przez kontener backendowy (jako root)...")
    run([
        "docker", "exec", "-u", "root", CONTAINER_NAME,
        "chown", "-R", "www-data:www-data", CONTAINER_STORAGE_PATH,
    ])

    # ------------------------------------------------------------------ krok 6
    step(6, "Weryfikacja")

    try:
        result = run_output([
            "docker", "exec", CONTAINER_NAME,
            "ls", "-la", f"{CONTAINER_STORAGE_PATH}/app/public/",
        ])
        print(f"  Zawartość {CONTAINER_STORAGE_PATH}/app/public/:")
        print(result)
    except subprocess.CalledProcessError:
        print("  (Katalog public/ może być pusty lub jeszcze nie istnieć)")

    print(f"\n  Wolumen {VOLUME_NAME}:")
    run(["docker", "volume", "inspect", VOLUME_NAME])

    # ------------------------------------------------------------------ koniec
    print(f"\n{'='*60}")
    print("  MIGRACJA ZAKOŃCZONA")
    print(f"{'='*60}")
    print(f"\n  Backup zapisany w : {BACKUP_DIR}")
    print(f"  Wolumen           : {VOLUME_NAME}")
    print(f"\n  Oryginalne pliki pozostają w: {SOURCE_STORAGE}")
    print(f"  (bind-mount zastąpiony wolumenem — backend/storage/ nie jest już używany)")
    print(f"\n  Możesz usunąć backup gdy wszystko działa:")
    print(f"    rm -rf {BACKUP_DIR}")
    print()


if __name__ == "__main__":
    main()
