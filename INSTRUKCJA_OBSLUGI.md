># 📘 Hi.Events – Pełna instrukcja obsługi

> Wersja: maj 2026  
> Aplikacja: Hi.Events – platforma do zarządzania wydarzeniami i sprzedaży biletów

---

## Spis treści

1. [Logowanie i konto](#1-logowanie-i-konto)
2. [Getting Started – pasek startowy](#2-getting-started--pasek-startowy)
3. [Tworzenie wydarzenia](#3-tworzenie-wydarzenia)
4. [Bilety i produkty](#4-bilety-i-produkty)
5. [Kody promocyjne](#5-kody-promocyjne)
6. [Pytania do uczestników](#6-pytania-do-uczestników)
7. [Uczestnicy](#7-uczestnicy)
8. [Zamówienia](#8-zamówienia)
9. [Faktury](#9-faktury)
10. [Wiadomości](#10-wiadomości)
11. [Listy odprawy (Check-in)](#11-listy-odprawy-check-in)
12. [Afiliacje](#12-afiliacje)
13. [Ustawienia wydarzenia](#13-ustawienia-wydarzenia)
14. [Kreator strony wydarzenia](#14-kreator-strony-wydarzenia)
15. [Projektant biletów PDF](#15-projektant-biletów-pdf)
16. [Raporty](#16-raporty)
17. [Widget osadzony](#17-widget-osadzony)
18. [Webhooki](#18-webhooki)
19. [Ustawienia konta](#19-ustawienia-konta)
20. [Profil użytkownika](#20-profil-użytkownika)
21. [Publiczne strony](#21-publiczne-strony)

---

## 1. 🔐 Logowanie i konto

- Wejdź na stronę aplikacji → automatyczne przekierowanie na `/auth/login`
- **Rejestracja** → `/auth/register`
- **Zapomniane hasło** → `/auth/forgot-password` → link resetujący na e-mail
- **Reset hasła** → `/auth/reset-password/{token}`
- **Akceptacja zaproszenia** → `/auth/accept-invitation/{token}`
- Po zalogowaniu → panel wydarzeń `/manage/events`

---

## 2. 🚀 Getting Started – pasek startowy

Po stworzeniu pierwszego wydarzenia pojawia się strona z 6 krokami i paskiem postępu (0–100%):

| # | Krok | Gdzie wykonać |
|---|------|---------------|
| 1 | 🎟️ Dodaj bilety | Tickets & Products |
| 2 | ⚡ Uzupełnij opis wydarzenia | Settings → Event Details |
| 3 | 💳 Połącz Stripe | Account → Payment |
| 4 | 🎨 Dodaj zdjęcia / dostosuj stronę | Homepage Designer |
| 5 | 🚀 Opublikuj wydarzenie | Kliknij „Set your event live" |
| 6 | ✉️ Potwierdź e-mail konta | Sprawdź skrzynkę pocztową |

---

## 3. 🎉 Tworzenie wydarzenia

Panel główny → przycisk **„Create Event"**.

### Formularz tworzenia:

| Pole | Szczegóły |
|------|-----------|
| **Kto organizuje?** | Wybierz istniejącego organizatora z listy **lub** kliknij „create an organizer" aby stworzyć nowego |
| **Nazwa wydarzenia** | Wymagane, maks. 150 znaków |
| **Kategoria** | Wybierz z listy kategorii (np. 🎵 Music, 🎤 Conference) |
| **Opis** | Edytor rich-text, maks. 2000 znaków |
| **Data i godzina startu** | Wymagane. Format 12h z wybierakiem kalendarza |
| **Data i godzina końca** | Opcjonalne. System automatycznie proponuje +2h po starcie |

Po zapisaniu → przekierowanie do strony **Getting Started** z paskiem postępu.

### Cykl życia wydarzenia:
```
DRAFT → LIVE → ARCHIVED
```
- **DRAFT** – niewidoczne publicznie, można swobodnie edytować
- **LIVE** – widoczne i dostępne do zakupu
- **ARCHIVED** – ukryte publicznie, można przywrócić

---

## 4. 🎟️ Bilety i produkty

Ścieżka: **Wydarzenie → Tickets & Products**

### Tworzenie biletu / produktu
Menu **„Create" → „Ticket or Product"**

#### Krok 1 – Typ produktu:
| Typ | Opis |
|-----|------|
| 🎫 **Ticket** | Bilet z kodem QR – system wystawia bilet przy zakupie |
| 👕 **General** | Produkt ogólny (koszulka, kubek itp.) – bez biletu |

#### Krok 2 – Typ ceny:
| Typ | Opis |
|-----|------|
| 💵 **Paid** | Stała cena |
| 🆓 **Free** | Bezpłatny, bez wymagania danych płatności |
| ❤️ **Donation** | Klient sam wpisuje kwotę (ustalasz minimalną) |
| 🪙 **Tiered** | Kilka progów cenowych (np. Early Bird / Standard / VIP) |

#### Krok 3 – Podstawowe pola:
- Nazwa produktu (wymagana)
- Opis (rich-text)
- Kategoria produktu (grupowanie na stronie publicznej)
- Cena i dostępna ilość (`puste = nielimitowana`)

#### Krok 4 – Opcje rozszerzone (rozwijane):

| Sekcja | Zawartość |
|--------|-----------|
| **Podatki i opłaty** | Przypisz zdefiniowane VAT / opłaty serwisowe |
| **Limity zamówienia** | Min. i maks. sztuk w jednym zamówieniu |
| **Okres sprzedaży** | Data startu i końca sprzedaży tego biletu |
| **Widoczność** | 6 opcji przełączników (patrz niżej) |
| **Wyróżnienie** | Podświetl bilet innym kolorem + własny napis np. „Selling fast 🔥" |

#### Opcje widoczności (przełączniki):
- Ukryj bilet przed datą startu sprzedaży
- Ukryj bilet po dacie końca sprzedaży
- Zwiń bilet domyślnie na stronie (collapsed)
- Pokaż pozostałą ilość na stronie
- Ukryj gdy wyprzedany
- 🔒 **Ukryj bez kodu promo** – bilet niewidoczny dopóki klient nie wpisze kodu
- Całkowicie ukryj produkt
- Włącz listę oczekujących (waitlist) gdy wyprzedany

#### Bilety warstwowe (Tiered):
Każdy tier ma: cenę, etykietę (np. „Early Bird"), ilość, daty sprzedaży, opcję ukrycia.  
⚠️ Nie można usunąć tiera, jeśli ktoś już go kupił.

### Kategorie produktów
**„Create" → „Category"** – nadaj nazwę.  
Kategorii używasz do grupowania biletów/produktów na stronie publicznej (np. „Bilety VIP", „Merchandise").

---

## 5. 🏷️ Kody promocyjne

Ścieżka: **Wydarzenie → Promo Codes**

### Formularz tworzenia kodu:

| Pole | Opis |
|------|------|
| **Kod** | Wpisz ręcznie (np. `PROMO20`) lub kliknij **„Generate code"** |
| **Typ zniżki** | Brak zniżki / Procentowa / Kwotowa stała |
| **Wartość zniżki** | Procent lub kwota w walucie wydarzenia |
| **Produkty** | Do których biletów/produktów kod obowiązuje (domyślnie: wszystkie) |
| **Data ważności** | Opcjonalnie: kiedy kod przestaje działać |
| **Limit użyć** | Ile razy można użyć kodu (puste = bez limitu) |

> 💡 **Wskazówka:** Kod bez zniżki (typ „No Discount") służy **wyłącznie do odblokowania ukrytych biletów** (tych z opcją „Ukryj bez kodu promo").

---

## 6. ❓ Pytania do uczestników

Ścieżka: **Wydarzenie → Questions**

### Tworzenie pytania:

#### Krok 1 – Komu zadać pytanie:
| Opcja | Opis |
|-------|------|
| 📋 **Raz na zamówienie** | Jedno pytanie dla całego zamówienia (np. „Adres dostawy") |
| 👤 **Raz na produkt** | Pytanie dla każdej sztuki (np. „Rozmiar koszulki") |

#### Krok 2 – Typ pytania:
| Typ | Opis |
|-----|------|
| Pojedyncza linia tekstu | Krótka odpowiedź |
| Wieloliniowy tekst | Dłuższa odpowiedź |
| Checkboxy | Wielokrotny wybór |
| Radio | Jednokrotny wybór z opcji |
| Dropdown | Lista rozwijana – jeden wybór |
| Adres | Pola adresowe (ulica, miasto, kraj) |
| Data | Pole daty (np. data urodzenia) |

#### Krok 3 – Treść i opcje:
- Tytuł pytania (wymagany)
- Opcjonalny opis / dodatkowe instrukcje (do 10 000 znaków)
- Dla checkbox/radio/dropdown: dodaj opcje odpowiedzi

#### Przełączniki:
- ✅ **Obowiązkowe** – klient musi odpowiedzieć przed zakupem
- 👁️ **Ukryte** – tylko organizator widzi odpowiedzi, klient nie

---

## 7. 👥 Uczestnicy

Ścieżka: **Wydarzenie → Attendees**

### Lista uczestników:
- Wyszukaj po: imieniu, nazwisku, e-mailu, numerze zamówienia
- Filtruj po: **typie biletu** (w tym tiery) i **statusie**:
  - Active / Cancelled / Awaiting Payment
- 📥 Eksportuj do pliku **XLSX**

### Ręczne dodanie uczestnika (przycisk „Create"):

| Pole | Opis |
|------|------|
| Imię i nazwisko | Wymagane |
| E-mail | Wymagany |
| Język komunikacji | W tym języku uczestnik otrzyma e-maile |
| Typ biletu | Wybór z listy (z tierami) |
| Kwota zapłacona | Ręczna kwota bez Stripe |
| Kwoty podatków/opłat | Jeśli bilet ma przypisane podatki |
| ✅ Wyślij e-mail z potwierdzeniem | Opcjonalne – wyślij bilet i potwierdzenie |

---

## 8. 📦 Zamówienia

Ścieżka: **Wydarzenie → Orders**

### Filtrowanie:
- **Status zamówienia:** Completed / Cancelled / Awaiting Offline Payment
- **Status zwrotu:** Refunded / Partially Refunded

### Szczegóły zamówienia (kliknij w zamówienie):
Otwiera boczny panel z zakładkami:

**Zakładka „View"** – accordion z sekcjami:
| Sekcja | Zawartość |
|--------|-----------|
| Order Details | Numer ref., data, kwoty |
| Order Notes | Notatki wewnętrzne (niewidoczne dla klienta) |
| Order Summary | Podsumowanie biletów i kwot |
| Questions & Answers | Odpowiedzi klienta na pytania |
| Attendees | Lista uczestników w zamówieniu |

**Zakładka „Edit"** – edycja:
- Imię, nazwisko, e-mail zamawiającego
- Notatki wewnętrzne

### Zwrot pieniędzy (przycisk „Refund"):
- Widoczna łączna kwota, już zwrócona, dostępna do zwrotu
- Wpisz kwotę zwrotu (domyślnie: pełna pozostała kwota)
- System informuje czy to **zwrot częściowy** czy **pełny**
- ✅ Wyślij e-mail z potwierdzeniem zwrotu do klienta
- ✅ Anuluj zamówienie (zwalnia bilety do puli)

> ⚠️ Nie można zwrócić zamówień tworzonych ręcznie ani tych już zwróconych.

### Eksport zamówień:
Przycisk **„Export"** → plik **XLSX** ze wszystkimi zamówieniami.

---

## 9. 🧾 Faktury

### Krok 1 – Włączenie fakturowania

Ścieżka: **Ustawienia wydarzenia → Payment & Invoicing → Invoice Settings**

Włącz przełącznik **„Enable Invoicing"**, a następnie skonfiguruj:

| Pole | Opis |
|------|------|
| **Document Label** | Nazwa dokumentu (domyślnie „Invoice", możesz wpisać „Faktura VAT") |
| **Number Prefix** | Przedrostek numeru – np. `INV-` lub `FV-` |
| **First Invoice Number** | Numer startowy (np. 1). ⚠️ Nie można zmienić po wygenerowaniu faktur |
| **Payment Due Period** | Termin płatności w dniach (puste = brak terminu) |
| **Organization Name** | Nazwa Twojej firmy |
| **Organization Address** | Adres firmy (rich-text) |
| **Tax Details** | NIP, VAT EU, numer rejestracyjny itp. |
| **Invoice Notes** | Dodatkowe uwagi (warunki płatności, polityka zwrotów) |

### Jak faktury są dystrybuowane:
- Automatycznie generowane przy każdym zakupie
- Wysyłane razem z e-mailem potwierdzającym zamówienie
- Klient może pobrać ze strony podsumowania zamówienia lub z linku „Moje bilety"

### Pobieranie faktury przez organizatora:
**Panel → Zamówienia → menu `⋮` → „Download invoice"**  
Plik PDF z nazwą = numer faktury (np. `FV-0001.pdf`).

> ⚠️ Faktury generowane są tylko dla zamówień złożonych **po włączeniu** opcji „Enable Invoicing".

---

## 10. 💬 Wiadomości

Ścieżka: **Wydarzenie → Messages**

### Nowa wiadomość (przycisk „Compose"):

#### Odbiorcy – wybierz grupę:
| Opcja | Opis |
|-------|------|
| Attendees with a specific ticket | Uczestnicy z konkretnym biletem |
| All attendees | Wszyscy uczestnicy wydarzenia |
| Order owners with a specific product | Właściciele zamówień z konkretnym produktem |

#### Treść:
- Temat e-maila (wymagany)
- Treść (edytor rich-text)

#### Kiedy wysłać:
| Opcja | Opis |
|-------|------|
| **Send now** | Natychmiast |
| **1 tydzień przed wydarzeniem** | Preset |
| **1 dzień przed wydarzeniem** | Preset |
| **1 godzinę przed wydarzeniem** | Preset |
| **1 dzień po dacie startu** | Preset |
| **1 dzień po dacie końca** | Preset |
| **Custom date and time** | Dowolna data i godzina |

#### Dodatkowe opcje (menu przy przycisku wyślij):
- 🧪 **Send as test** – wyślij testową wersję do siebie
- 📋 **Send me a copy** – wyślij kopię na swój e-mail

> ⚠️ **Wymagana zgoda:** Musisz zaznaczyć checkbox potwierdzający, że to wiadomość transakcyjna (nie promocyjna). Wiadomości promocyjne mogą skutkować zawieszeniem konta.

### Historia wiadomości:
- Widok mail-like (lista po lewej, podgląd po prawej)
- Zaplanowane wiadomości można **anulować**
- Statusy: Sent / Failed / Scheduled / Cancelled
- Kliknij na odbiorcę → lista wszystkich adresatów (po wysyłce)

---

## 11. ✅ Listy odprawy (Check-in)

Ścieżka: **Wydarzenie → Check-In Lists**

### Tworzenie listy odprawy:

| Pole | Opis |
|------|------|
| **Nazwa** | Np. „Wejście główne" / „Strefa VIP" |
| **Bilety** | Które typy biletów obsługuje ta lista |
| **Opis** | Widoczny tylko obsłudze – opis dla personelu |
| **Data aktywacji** | Od kiedy można skanować |
| **Data wygaśnięcia** | Do kiedy można skanować |

Po zapisaniu automatycznie pojawia się modal z **linkiem i QR kodem** do listy odprawy.  
Ten link możesz udostępnić obsłudze – **nie wymaga logowania do systemu**.

### Użycie w terenie:
Otwórz link `/check-in/{short_id}` na telefonie lub tablecie. Skanowanie kodu QR z biletu pokazuje:
- ✅ **Ważny** – wejście dozwolone
- ❌ **Już zeskanowany** – próba ponownego wejścia
- ❌ **Nieważny** – zły bilet lub nieodpowiedni typ

---

## 12. 🔗 Afiliacje

Ścieżka: **Wydarzenie → Affiliates**

### Tworzenie afiliatu (przycisk „Create Affiliate"):

| Pole | Opis |
|------|------|
| **Nazwa** | Nazwa partnera / influencera |
| **Kod** | Unikalny kod (litery, cyfry, `-`, `_`; 3–20 znaków) lub **Generate** |
| **E-mail** | Opcjonalnie – do kontaktu z partnerem |
| **Status** | Active / Inactive |

System generuje unikalny link do Twojego wydarzenia z kodem afiliata.  
Wszystkie zakupy przez ten link są śledzone i widoczne w raportach.

---

## 13. ⚙️ Ustawienia wydarzenia

Ścieżka: **Wydarzenie → Settings**

### Event Details
- Nazwa, kategoria, opis
- Data startu i końca
- Strefa czasowa
- Waluta (⚠️ nie można zmienić po ustawieniu)

### Location
- Adres fizyczny lub oznaczenie jako online
- Kraj, miasto, ulica, mapa

### Checkout
| Opcja | Opis |
|-------|------|
| Pre-checkout message | Komunikat wyświetlany **przed** płatnością (np. regulamin) |
| Post-checkout message | Komunikat wyświetlany **po** zakupie (np. „Dziękujemy!") |
| Attendee info collection | `Per ticket` – każdy bilet osobno / `Per order` – dane zamawiającego dla wszystkich |
| Order timeout | Ile minut klient ma na dokończenie zakupu (domyślnie 15 min) |
| Marketing opt-in | Checkbox na checkout do zapisu na listę mailingową |

### SEO
- Tytuł strony (meta title)
- Opis (meta description)
- Open Graph (podgląd na Facebooku, Twitterze)

### Email & Templates
- Nadawca e-maili
- Szablony wiadomości: potwierdzenie zamówienia, bilet, itp.

### Waitlist
| Opcja | Opis |
|-------|------|
| Auto-Process Waitlist | Automatycznie oferuje miejsca gdy bilet się zwolni |
| Offer Timeout | Ile minut ma klient na zakup po otrzymaniu oferty (1–10 080 min; puste = bez limitu) |

### Payment & Invoicing
| Sekcja | Opis |
|--------|------|
| Payment Methods | Stripe / Płatności offline (przelew, gotówka) |
| Billing Settings | Wymagaj adresu rozliczeniowego podczas checkout |
| Invoice Settings | Konfiguracja faktur (patrz sekcja 9) |

**Płatności offline:**
- Klient otrzymuje bilety od razu z oznaczeniem „nieopłacone"
- Organizator musi ręcznie oznaczyć zamówienie jako opłacone
- Możliwość wpuszczania nieopłaconych uczestników na odprawie (przełącznik)

### Danger Zone *(tylko Administrator)*
| Akcja | Opis |
|-------|------|
| **Usuń wydarzenie** | Trwałe, wymaga wpisania słowa „delete". Możliwe tylko bez sprzedanych biletów |
| **Zarchiwizuj** | Ukrywa publicznie, można przywrócić w dowolnym momencie |
| **Przywróć** | Odwraca archiwizację |

---

## 14. 🎨 Kreator strony wydarzenia

Ścieżka: **Wydarzenie → Homepage Designer**

- Edytor drag-and-drop strony publicznej
- Dodawaj/usuwaj sekcje
- Wgrywaj zdjęcia okładkowe i galerie
- Dostosuj kolory i układ
- Podgląd na żywo przed zapisaniem
- Strona organizatora ma własny kreator: **Organizer → Organizer Homepage Designer**

---

## 15. 🖨️ Projektant biletów PDF

Ścieżka: **Wydarzenie → Ticket Designer**

- Edytor wizualny biletów PDF
- Wybierz układ i kolory
- Dodaj logo organizatora
- Podgląd wydruku przed zapisaniem
- Opcja druku testowego

---

## 16. 📊 Raporty

Dostępne na poziomie **wydarzenia** i **organizatora**.

### Dostępne raporty:
- Sprzedaż biletów
- Podatki i opłaty
- Kody promocyjne
- Afiliacje (śledzenie linków)

Każdy raport można przeglądać w przeglądarce lub eksportować.

---

## 17. 📎 Widget osadzony

Ścieżka: **Wydarzenie → Widget**

- Skopiuj gotowy kod HTML/JavaScript
- Wklej na swoją stronę internetową
- Widget wyświetla bilety i formularz zakupu **bezpośrednio na Twojej stronie** bez przekierowania
- URL widgetu: `/widget/{eventId}`

---

## 18. 🔔 Webhooki

Dostępne na poziomie **wydarzenia** i **organizatora**.

### Tworzenie webhooka:
- URL endpointu (Twój serwer / Zapier / Make / n8n)
- Zdarzenia do nasłuchiwania: nowe zamówienie, anulowanie, odprawa itp.

### Historia wywołań:
- Logi każdego wywołania webhooka
- Sprawdź czy webhook zadziałał
- Podgląd treści wysłanego żądania (payload)

---

## 19. 🏢 Ustawienia konta

Ścieżka: `/account/`

### Account Settings
- Nazwa konta / organizacji
- Logo i branding
- Waluta domyślna

### Taxes & Fees
Globalne podatki i opłaty (przypisujesz je potem do biletów):

| Pole | Opis |
|------|------|
| Nazwa | Np. „VAT 23%" |
| Typ | Tax (podatek) lub Fee (opłata) |
| Sposób naliczania | Procentowy lub Kwotowy |
| Stawka | Np. 23 (%) |
| Domyślny | Automatycznie przypisywany do nowych produktów |

### Event Defaults
Domyślne ustawienia dla nowych wydarzeń (waluta, strefa czasowa itp.)

### Users – zarządzanie zespołem

**Zapraszanie użytkownika** (przycisk „Invite User"):

| Pole | Opis |
|------|------|
| E-mail | Adres osoby zapraszanej |
| Rola | **Admin** – pełen dostęp / **Organizer** – ograniczony |

**Zarządzanie istniejącymi użytkownikami:**
| Akcja | Opis |
|-------|------|
| Edytuj rolę | Zmień rolę użytkownika |
| Resend invitation | Ponownie wyślij zaproszenie (jeśli status: INVITED) |
| Revoke invitation | Cofnij zaproszenie |

**Statusy użytkowników:**
- `ACTIVE` – aktywny
- `INVITED` – zaproszony, jeszcze się nie zalogował
- `INACTIVE` – nieaktywny

### Payment
Podpięcie **Stripe Connect** – wymagane do:
- Przyjmowania płatności kartą
- Wysyłania wiadomości do uczestników (weryfikacja antyspamowa)
- Środki trafiają bezpośrednio na Twoje konto Stripe

---

## 20. 👤 Profil użytkownika

Ścieżka: `/manage/profile`

- Zmiana imienia i nazwiska
- Zmiana adresu e-mail (link potwierdzający wysyłany na nowy adres)
- Zmiana hasła

---

## 21. 🌐 Publiczne strony

| URL | Opis |
|-----|------|
| `/e/{eventId}/{slug}` | Strona wydarzenia (nowy format) |
| `/event/{eventId}/{slug}` | Strona wydarzenia (klasyczny format) |
| `/events/{organizerId}/{slug}` | Strona organizatora ze wszystkimi wydarzeniami |
| `/events/{organizerId}/{slug}/past-events` | Minione wydarzenia organizatora |
| `/checkout/{eventId}` | Koszyk zakupowy |
| `/my-tickets/{token}` | Strona „moje bilety" dla klienta |
| `/check-in/{shortId}` | Panel odprawy (bez logowania) |
| `/product/{eventId}/{attendeeId}` | Podgląd biletu uczestnika |
| `/widget/{eventId}` | Osadzony widget biletów |

---

## 📞 Wsparcie

- 📖 Dokumentacja: [hi.events/docs](https://hi.events/docs)
- 📧 E-mail: [hello@hi.events](mailto:hello@hi.events)
- 🐛 Zgłoszenia błędów: [GitHub Issues](https://github.com/HiEventsDev/hi.events/issues)

