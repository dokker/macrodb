# Nutrition tracking backend – specifikáció

Sep 28, 2026 · @Someone

## Cél és hatókör

Saját étkezésnapló backend, amit elsősorban egy LLM (Claude vagy más MCP-képes kliens) kezel MCP szerveren keresztül. A felhasználó természetes nyelven mondja el, mit evett, az LLM ezt élelmiszerekre és grammokra bontja, a backend pedig a saját adatbázisából számolja a makrókat.

Az első verzióban benne van:

- élelmiszer-keresés név és vonalkód alapján
- kész fogások kiválasztása (rántott hús, pörkölt, főzelékek) adagméretekkel
- étkezések rögzítése, módosítása, törlése
- napi és étkezésenkénti makró-összesítés (kcal, fehérje, szénhidrát, zsír); rost és cukor, ha a forrásadat tartalmazza
- napi célértékek (kcal, makrók) és az eltérés kijelzése
- saját élelmiszerek és receptek felvitele

A második körben: fotó alapú felvitel a backenden keresztül, és egy multiplatform frontend mobilra.

Nincs benne az első verzióban: saját frontend, edzéskövetés, többfelhasználós működés (egyetlen felhasználó, nincs `users` tábla).

## Architektúra

Egy alkalmazás, két bejárattal: az MCP szerver az LLM-nek, a REST API minden másnak. Mindkettő ugyanazt az alkalmazásréteget hívja, így a logika egy helyen van.

&#91;embedded content: komponensek · 2 bejárat, 1 alkalmazásréteg\]

Vonalkódos keresésnél a backend először a saját adatbázisában keres, és csak találat nélkül kérdezi le élőben az Open Food Facts API-t, majd elmenti az eredményt. Az import job a dumpok szűrt változatát tölti be ütemezetten.

Stack: Laravel (PHP 8.5) és MariaDB a meglévő tárhelyen; Docker csak a helyi fejlesztéshez. Az MCP szerver lehet ugyanannak az alkalmazásnak egy végpontja (Streamable HTTP transport), nem kell külön szolgáltatásnak lennie.

## Adatforrások

Minden forrás ugyanabba a `foods` táblába kerül, a `source` mező mondja meg, honnan jött.

| Forrás | Mire jó | Betöltés | Licenc |
| --- | --- | --- | --- |
| Open Food Facts | bolti termékek vonalkóddal | szűrt dump (releváns országok, csak tápérték-mezők) + élő API fallback | ODbL |
| USDA FoodData Central | alapélelmiszerek, megbízható mérések | dump (Foundation, SR Legacy, FNDDS) | CC0 |
| Saját felvitel | hiányzó termékek, házi ételek, receptek | MCP tool vagy REST | saját |

Az USDA nevek angolok, ezért kell egy `food_aliases` tábla a magyar nevekhez. Ezt az LLM is töltheti, amikor egy keresés angol találatot ad vissza.

## Adatmodell

A tápértékek mindig 100 g-ra (folyadéknál 100 ml-re) vannak tárolva, a napló grammban rögzít, az összesítés ebből számol.

| Tábla | Fő mezők | Megjegyzés |
| --- | --- | --- |
| `foods` | id, source, external\_id, barcode, name, brand, kcal, protein, carbs, fat (100 g-ra), fiber, sugar (nullable), serving\_size\_g | barcode egyedi index, name-re sima index (keresés: lásd Nem funkcionális követelmények) |
| `food_aliases` | food\_id, name, lang | magyar és egyéb keresőnevek |
| `food_portions` | food\_id vagy recipe\_id, label, grams | pl. „1 szelet”, „1 bögre”, „1 tányér” |
| `recipes` | id, name, total\_weight\_g, default\_portion\_g, is\_dish | saját receptek és kész fogások; total\_weight\_g a kész étel tömege |
| `recipe_items` | recipe\_id, food\_id, grams | a fogás makrói ebből jönnek |
| `meals` | id, eaten\_at, meal\_type, note | meal\_type: reggeli, ebéd, vacsora, snack |
| `meal_items` | meal\_id, food\_id vagy recipe\_id, grams, source\_text, input\_method | input\_method: szöveg, vonalkód, fotó |
| `daily_targets` | valid\_from, kcal, protein, carbs, fat | napi célok, az első verzió része |

A napi összesítés egy nézet vagy lekérdezés a `meal_items` és a `foods` táblák fölött, nem tárolt adat. Így egy utólagos javítás azonnal látszik.

## MCP eszközök

Kevés, jól körülhatárolt tool kell. Az LLM keres, választ, majd naplóz; a makrókat soha nem ő becsüli, hanem a backend számolja.

| Tool | Bemenet | Kimenet |
| --- | --- | --- |
| `search_foods` | query, limit | találatok id-vel, névvel, márkával, makrókkal 100 g-ra |
| `get_food_by_barcode` | barcode | egy élelmiszer, vagy „nincs találat” |
| `log_meal` | eaten\_at, meal\_type, items: \[{food\_id vagy recipe\_id, grams, source\_text}\] | a rögzített étkezés és makrói |
| `update_meal_item` / `delete_meal_item` | item\_id, grams | a módosított étkezés |
| `get_daily_summary` | date | napi összesen, étkezésenkénti bontás, cél-eltérés |
| `get_range_summary` | from, to | napi összesítések listája |
| `create_food` | név, makrók 100 g-ra, barcode? | az új élelmiszer id-je |
| `create_recipe` | név, összetevők grammal | a recept id-je és makrói |

Tipikus menet: a felhasználó azt írja, hogy „reggelire 2 tojás és 60 g zabpehely tejjel”. Az LLM meghívja a `search_foods`-t tételenként, kiválasztja a legjobb találatot, a darabszámot a `food_portions` alapján grammra váltja, majd egy `log_meal` hívással rögzít, és visszaigazolja a számokat.

Fotóval két út van: Claude-ban maga a kliens nézi meg a képet és hívja a `log_meal`-t, a saját frontendről pedig a backend végzi a felismerést (lásd a Fotó alapú felvitel szakaszt).

## Fotó alapú felvitel

Backend oldalon megoldható, közepes többletmunkával, és nem kell hozzá újratervezni semmit. A vision modell csak megnevezi az ételeket és grammot becsül; a makrókat továbbra is a saját adatbázis számolja. Ezért a fotós felvitel a második körbe kerül, a meglévő keresésre és naplózásra épülve.

Folyamat:

1. A kliens feltölti a képet: `POST /meals/photo` (kép, opcionálisan egy rövid megjegyzés, pl. „fél adag”, „olajban sütve”).
2. A backend fix prompttal és JSON sémával elküldi a vision modellnek. A válasz: `[{name_hu, name_en, grams, confidence}]`.
3. Minden tételt a `search_foods` logikával párosít a `foods` és `recipes` táblákból.
4. Piszkozatot ad vissza, nem ment. A grammok és a találatok itt javíthatók.
5. Jóváhagyáskor ugyanaz a `log_meal` fut, mint minden más úton.

Modell: a Gemini API ingyenes szintje a Flash és Flash-Lite modelleket díjmentesen adja, bankkártya nélkül ([árazás](https://ai.google.dev/gemini-api/docs/pricing)). Cserébe az ingyenes szinten beküldött tartalmat a Google felhasználhatja a termékei fejlesztésére; ételfotóknál ez elfogadható. Fizetős szinten a Gemini 3.1 Flash-Lite 0,25 USD / 1M bemeneti token, ami egy napi pár fotónál elhanyagolható. A hívás egy `VisionProvider` interfész mögé kerül, így a modell később cserélhető.

Mi jön hozzá a projekthez: egy végpont, egy provider adapter, a JSON-válasz validálása, a párosítás és a piszkozat/jóváhagyás lépés. Saját modell tanítására vagy képtárolásra nincs szükség.

Pontosság: az étel felismerése általában jó, a gramm becslése a gyenge pont (rejtett olaj, szósz, vegyes tányér, a tányér mérete). Emiatt a jóváhagyó lépés kötelező, és sokat segít, ha a fotó mellé egy rövid szöveges megjegyzés is jár.

## Kész ételek (fogások)

A fogások a `recipes` táblában élnek, és a keresés az élelmiszerekkel együtt adja vissza őket. Magyar ételekre nincs szabadon használható adatbázis, ezért három forrásból érdemes összerakni:

| Forrás | Mit ad | Megjegyzés |
| --- | --- | --- |
| USDA FNDDS (a FoodData Central „Survey” része) | elkészített ételek, pl. panírozott, sült sertésszelet | angol nevek, amerikai receptúra |
| Open Food Facts | bolti készételek, fagyasztott termékek | vonalkóddal, ha van a csomagon |
| Saját receptek | magyar fogások: rántott hús, pörkölt, főzelékek, levesek | összetevőkből számolva, egyszeri feltöltéssel |

A saját fogások összetevőkből épülnek (pl. rántott hús: sertéskaraj, liszt, tojás, zsemlemorzsa, felszívott olaj). A kész étel tömegét is tárolni kell, mert sütéskor és főzéskor víz távozik, így a 100 g-ra vetített érték a kész tömegből jön. A kezdeti listát (pár tucat gyakori fogás) egy LLM összeállíthatja az USDA-alapanyagokból, te pedig átnézed.

Adagok: minden fogásnak van alapértelmezett adagja (`default_portion_g`) és néhány nevesített adagja (pl. „1 szelet”, „1 tányér”), így a „két szelet rántott hús” gramm nélkül is rögzíthető.

Kezdő receptlista: zabkása mogyoróvajjal, kávé zabtejjel, rizibizi, kínai hagymás csirke, rántott csirkemáj.

## REST API

Ugyanazok a műveletek, mint az MCP tooloknál, egy későbbi webes vagy mobil klienshez.

| Metódus | Útvonal | Megfelelő tool |
| --- | --- | --- |
| GET | `/foods?q=` | `search_foods` (fogásokat is ad) |
| GET | `/foods/barcode/{code}` | `get_food_by_barcode` |
| POST | `/foods` | `create_food` |
| POST | `/recipes` | `create_recipe` |
| POST | `/meals` | `log_meal` |
| POST | `/meals/photo` | nincs; piszkozatot ad, a mentés `POST /meals` |
| PATCH / DELETE | `/meal-items/{id}` | `update_meal_item` / `delete_meal_item` |
| GET | `/summary/daily?date=` | `get_daily_summary` |
| GET | `/summary/range?from=&to=` | `get_range_summary` |

## Nem funkcionális követelmények

- Hitelesítés: a Claude távoli MCP connectorjaihoz OAuth kell, a REST API-hoz elég egy személyes API token.
- Időzóna: minden időpont UTC-ben tárolva, a napi összesítés Europe/Budapest szerint vág napot.
- Futtatás: a meglévő PHP 8.5 + MariaDB tárhely, cronból hívott Laravel ütemező (import, adatbázis alapú queue), publikus HTTPS végpont az MCP-hez. A dumpokat helyben érdemes szűrni, és csak a kész adatot feltölteni, mert a teljes OFF-adatbázis túl nagy egy megosztott tárhelyre.
- Keresés: magyarul, részszóra is („zab” → zabpehely, zabtej, és az USDA „Oats” a magyar aliasán keresztül). Ezért részsztring-alapú (LIKE) keresés a neveken és az aliasokon, rangsorolva (pontos egyezés, szókezdet, tartalmazás), nem FULLTEXT: a FULLTEXT csak szókezdetre illeszt, így nem találja meg a „tej”-et a „zabtej”-ben, és a 3 karakternél rövidebb szavakat („só”) kihagyja. A utf8mb4\_unicode\_ci kolláció miatt kis- és nagybetű, valamint ékezet nélkül is talál („rantott” → rántott).
- Licenc: az OFF-adatot csak saját használatra tárolod, amíg nem teszed közzé, az ODbL megosztási kötelezettsége nem érint.

## Nyitott kérdések

- [x] Laravel vagy ASP.NET Core legyen az alap?
- [x] Frontend: PWA, Flutter vagy React Native?
- [x] Mely fogásokkal induljon a saját receptlista?
- [x] Elfogadható-e, hogy az ingyenes Gemini szinten a Google felhasználhatja a fotókat?
- [x] Legyen-e napi célérték és eltérés-kijelzés az első verzióban?

Eldöntve: az alap Laravel, a frontend PWA. Böngészőben fut, iPhone-on a kezdőképernyőre tehető, natív build nem kell; a kamera és a vonalkódolvasás (html5-qrcode vagy ZXing) böngészőből is működik. A fotófelismeréshez a Gemini ingyenes szintje marad, a képek felhasználása a Google részéről elfogadható. A napi célértékek és az eltérés kijelzése már az első verzióba kerül.
