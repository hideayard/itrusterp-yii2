# iTrustERP Yii2 API (`aureuserp-yii2`)

Backend REST API untuk **iTrustERP** — hasil migrasi dari `aureuserp-master` (Laravel 13 + Filament 5).
Dipakai oleh frontend **Astro**. Database **dipakai ulang** (schema `aureuserp` yang sama,
tanpa migrasi ulang), sehingga data yang sudah ada tetap jalan.

- Framework: **Yii2 2.0.55** (API-only, tanpa view)
- Auth: **Bearer token (Sanctum-compatible)** — tabel `personal_access_tokens`
- Permission: mengikuti tabel **Spatie Permission** milik Laravel
- Cakupan saat ini: **Security (auth), Support, Partners, Products, Sales, Purchases, Inventories, Accounts, Projects, Timesheets, Manufacturing, Website & Blog, Recruitments, Time-off, Maintenance, System (plugins & table views), Point of Sale, Employees**

---

## 1. Kebutuhan

- PHP **8.2+** (diuji di 8.3.16)
- MySQL/MariaDB dengan database `aureuserp` (dari iTrustERP)
- Composer `vendor/` sudah terpasang

## 2. Konfigurasi Database

`config/db.php` membaca environment variable (default = database produksi lokal):

| Env | Default |
|---|---|
| `AUREUS_DB_HOST` | `localhost` |
| `AUREUS_DB` | `aureuserp` |
| `AUREUS_DB_USER` | `root` |
| `AUREUS_DB_PASSWORD` | *(kosong)* |

Sesi MySQL dipaksa `time_zone = '+00:00'` (via `PDO::MYSQL_ATTR_INIT_COMMAND`) agar timestamp konsisten UTC.

## 3. Menjalankan

**A. PHP built-in server (paling cepat):**

```powershell
cd E:\laragon6\www\ERP\aureuserp-yii2
php -S 127.0.0.1:8899 -t web web/index.php
```

**B. Lewat Apache Laragon** (`web/.htaccess` sudah disediakan):

```
http://localhost/ERP/aureuserp-yii2/web/admin/api/v1/...
```

**C. `yii serve`:** `php yii serve --port=8899 --docroot=@app/web`

Cek cepat: `php yii help` harus menampilkan daftar command Yii.

## 4. Autentikasi
```
POST /admin/api/v1/login
Content-Type: application/json

{ "login": "admin@example.com", "password": "..." }
```

Field `login` menerima **email atau username** (didukung sejak kolom `username` ditambahkan):

- Mengandung `@` → dicari sebagai **email** (divalidasi format email).
- Tanpa `@` → dicari sebagai **username** (tidak peka huruf besar/kecil, mengikuti collation DB).
- Kunci lama `email` masih diterima untuk kompatibilitas (`{"email": "..."}`).

```json
{
  "message": "Login successful",
  "token": "1|e42d4e6c...",
  "token_type": "Bearer",
  "user": { "id": 1, "name": "Admin", "username": "admin", "email": "admin@example.com" }
}
```

Token **kompatibel dengan Sanctum**: disimpan sebagai `sha256(plain)` di `personal_access_tokens`,
format `id|plain`, `tokenable_type = Webkul\Security\Models\User` — jadi token bisa dipakai
bolak-balik antara aplikasi Laravel dan Yii2.

Pakai di setiap request: `Authorization: Bearer 1|e42d4e6c...`

**Logout**: `POST /admin/api/v1/logout` (menghapus token aktif).
**Throttle login**: 5 percobaan / 60 detik per `identifier|ip` → `429`.

**Kolom `username` pada `users`**: `VARCHAR(64) NULL` + unique index, diisi otomatis dari bagian
lokal email saat migrasi (mis. `admin@example.com` → `admin`; duplikat diberi akhiran id).
User tanpa username tetap bisa login memakai email. Username juga tersedia di `apiFields` User
sehingga bisa diubah lewat API.

- **Masa berlaku token**: `PersonalAccessToken::createToken()` mengisi `expires_at` dari env
  `AUREUS_TOKEN_TTL_DAYS` (default **90 hari**). Response login menyertakan `expires_at` dan
  `expires_in` sehingga frontend menyetel umur cookie mengikuti token. Set env tersebut bila perlu
  TTL lain (mis. `AUREUS_TOKEN_TTL_DAYS=365`), lalu login ulang.

## 5. Daftar Endpoint

Semua di bawah prefix `/admin/api/v1` (sama persis dengan `routes/api.php` tiap plugin iTrustERP).

| Resource | Method / Path | Soft delete |
|---|---|---|
| Auth | `POST login`, `POST logout` | — |
| Currencies | `GET/POST currencies`, `GET/PUT/PATCH/DELETE currencies/{id}` | tidak |
| Currency rates | `.../currencies/{currency_id}/rates` (+ `{id}`) | tidak |
| Banks | CRUD `banks` + `POST {id}/restore`, `DELETE {id}/force` | ya |
| Countries | `GET countries`, `GET countries/{id}` (read-only) | tidak |
| States | full CRUD `states` | tidak |
| UOM categories | full CRUD `uom-categories` | tidak |
| UOMs | `.../uom-categories/{uom_category_id}/uoms` (+ restore/force) | ya |
| Titles | full CRUD `partners/titles` | tidak |
| Tags | full CRUD `partners/tags` (+ restore/force) | ya |
| Industries | full CRUD `partners/industries` (+ restore/force) | ya |
| Partners | full CRUD `partners/partners` (+ restore/force) | ya |
| Addresses | `.../partners/{partner_id}/addresses` (+ restore/force) | ya |
| Bank accounts | `.../partners/{partner_id}/bank-accounts` (+ restore/force) | ya |
| Categories | full CRUD `products/categories` | tidak |
| Tags | full CRUD `products/tags` (+ restore/force) | ya |
| Attributes | full CRUD `products/attributes` (+ restore/force) | ya |
| Attribute options | `.../products/attributes/{attribute_id}/options` | tidak |
| Products | full CRUD `products/products` (+ restore/force) | ya |
| Product attributes | `.../products/products/{product_id}/attributes` | tidak |
| Variants | `.../products/products/{product_id}/variants` (+ restore/force); `POST .../variants` = generate | ya |
| Packagings | full CRUD `products/packagings` | tidak |
| Price lists | full CRUD `products/price-lists` (payload `items` ikut) | tidak |
| Sales orders | full CRUD `sales/orders` (payload `lines` & `sales_order_tags`) + `POST {id}/confirm`, `{id}/cancel`, `{id}/set-as-quotation`, `{id}/toggle-lock`, **`{id}/invoice`**; `GET {id}/invoices` | ya |
| Sales order lines | `GET .../sales/orders/{order_id}/lines` dan `/{id}` (read-only) | tidak |
| Sales order invoices | `GET .../sales/orders/{order_id}/invoices` (read-only, dari `accounts_account_moves`) | — |
| Sales order deliveries | `GET .../sales/orders/{order_id}/deliveries` (read-only, dari `inventories_operations`) | — |
| Sales tags | full CRUD `sales/tags` | tidak |
| Purchase orders | full CRUD `purchases/purchase-orders` (payload `lines`) + `POST {id}/confirm`, `{id}/cancel`, `{id}/draft`, `{id}/toggle-lock`, `{id}/confirm-receipt-date`, **`{id}/bill`**; `GET {id}/receipts`, `{id}/bills` | tidak |
| Purchase order lines | `GET .../purchases/purchase-orders/{order_id}/lines` dan `/{id}` (read-only) | tidak |
| Purchase receipts | `GET .../purchases/purchase-orders/{order_id}/receipts` (read-only) | — |
| Purchase bills | `GET .../purchases/purchase-orders/{order_id}/bills` (read-only) | — |
| Vendor price lists | full CRUD `purchases/vendor-price-lists` (tabel `products_product_suppliers`) | tidak |
| Purchase agreements | `GET purchases/purchase-agreements` dan `/{id}` (read-only, tahap berikutnya) | — |
| Warehouses | full CRUD `inventories/warehouses` (+ restore/force) | ya |
| Locations | full CRUD `inventories/locations` (+ restore/force) | ya |
| Operation types | full CRUD `inventories/operation-types` (+ restore/force) | ya |
| Inventory tags | full CRUD `inventories/tags` (izin `inventory_tag` tidak ada di DB → 403, parity) | ya |
| Package types | full CRUD `inventories/package-types` | tidak |
| Storage categories | full CRUD `inventories/storage-categories` | tidak |
| Packages | full CRUD `inventories/packages` | tidak |
| Lots | full CRUD `inventories/lots` | tidak |
| Quantities | full CRUD `inventories/quantities` + `POST {id}/apply`, `{id}/clear` | tidak |
| Moves | `GET inventories/moves` (read-only) | tidak |
| Transfers | `GET inventories/receipts` · `deliveries` · `internal-transfers` · `dropships` (+ `/{id}`); aksi workflow `POST {id}/check-availability` · `todo` · `validate` · `cancel` · `return` | tidak |
| Chart of accounts | full CRUD `accounts/accounts` | tidak |
| Journals | full CRUD `accounts/journals` | tidak |
| Taxes | full CRUD `accounts/taxes` | tidak |
| Tax groups | full CRUD `accounts/tax-groups` | tidak |
| Payment terms | full CRUD `accounts/payment-terms` (+ restore/force) + nested `payment-terms/{id}/due-terms` | ya |
| Incoterms | full CRUD `accounts/incoterms` (+ restore/force) | ya |
| Fiscal positions | full CRUD `accounts/fiscal-positions` | tidak |
| Cash roundings | full CRUD `accounts/cash-roundings` | tidak |
| Accounting documents | `GET accounts/invoices` · `bills` · `credit-notes` · `refunds` (+ `/{id}`, include `lines`); posting: `POST accounts/invoices/{id}/post` · `{id}/reset-to-draft`, `POST accounts/bills/{id}/post` · `{id}/reset-to-draft` | tidak |
| Projects | full CRUD `projects/projects` (payload `tags`) (+ restore/force) | ya |
| Project tasks | full CRUD `projects/tasks` (payload `users`, `tags`) (+ restore/force) | ya |
| Project stages | full CRUD `projects/project-stages` (+ restore/force) | ya |
| Task stages | full CRUD `projects/task-stages` (+ restore/force) | ya |
| Milestones | full CRUD `projects/milestones` | tidak |
| Project tags | full CRUD `projects/tags` (+ restore/force) | ya |
| Timesheets | full CRUD `projects/timesheets` (tabel `analytic_records`; recompute jam tugas + rollup ke tugas induk) | tidak |
| Work centers | full CRUD `manufacturing/work-centers` (+ restore/force) | ya |
| Work center capacities | full CRUD `manufacturing/work-center-capacities` | tidak |
| Operations | full CRUD `manufacturing/operations` (+ restore/force) | ya |
| Bills of materials | full CRUD `manufacturing/bills-of-materials` (+ restore/force) | ya |
| BoM lines | full CRUD **bersarang** `manufacturing/bills-of-materials/{id}/lines` | tidak |
| Manufacturing orders | full CRUD `manufacturing/orders` (nomor otomatis `MO/…`) | tidak |
| Work orders | full CRUD `manufacturing/work-orders` | tidak |
| Loss types | full CRUD `manufacturing/loss-types` | tidak |
| Productivity losses | full CRUD `manufacturing/productivity-losses` | tidak |
| Website pages | full CRUD `website/pages` (+ restore/force, kolom multi-bahasa) | ya |
| Blog posts | full CRUD `blogs/posts` (payload `tags`, + restore/force) | ya |
| Blog categories | full CRUD `blogs/categories` (kolom multi-bahasa) | ya |
| Blog tags | full CRUD `blogs/tags` | ya |
| Applicants | full CRUD `recruitments/applicants` (payload `categories`, + restore/force) | ya |
| Candidates | full CRUD `recruitments/candidates` (payload `categories`, + restore/force) | ya |
| Recruitment stages | full CRUD `recruitments/stages` (legend default otomatis) | tidak |
| Applicant categories · degrees · refuse reasons | full CRUD `recruitments/categories` · `degrees` · `refuse-reasons` | tidak |
| Candidate skills | full CRUD `recruitments/candidate-skills` | tidak |
| Time off | full CRUD `time-off/leaves` (include employee/type/department) | tidak |
| Leave types | full CRUD `time-off/leave-types` (+ restore/force) | ya |
| Leave allocations | full CRUD `time-off/allocations` (include leaveType/employee/accrualPlan) | tidak |
| Accrual plans | full CRUD `time-off/accrual-plans` (include levels) | tidak |
| Accrual levels | full CRUD `time-off/accrual-levels` | tidak |
| Mandatory days | full CRUD `time-off/mandatory-days` | tidak |
| Equipments | full CRUD `maintenance/equipments` (include category/team/technician) | ya |
| Equipment categories | full CRUD `maintenance/equipment-categories` | tidak |
| Maintenance requests | full CRUD `maintenance/requests` (include equipment/stage/team) | ya |
| Maintenance stages | full CRUD `maintenance/stages` | tidak |
| Maintenance teams | full CRUD `maintenance/teams` (payload `users` pivot) | ya |
| Plugins | full CRUD `system/plugins` (payload `dependency_ids` pivot, include dependencies/dependents) | tidak |
| Table views | full CRUD `system/table-views` (kolom `filters` JSON) | tidak |
| Table view favorites | full CRUD `system/table-view-favorites` | tidak |
| Chatter messages | full CRUD `chatter/messages` (polymorphic `messageable_type`/`messageable_id`, filter `messageable_id`/`type`, include causer/company/attachments) | tidak |
| Chatter followers | full CRUD `chatter/followers` (polymorphic `followable_*`; duplikat → 422) | tidak |
| Chatter attachments | full CRUD `chatter/attachments` (metadata + relasi `message`) | tidak |
| Point of Sale | `GET point-of-sale/registers`, `configs/{id}/catalog` (kini termasuk `payment_methods`), `configs/{id}/session`, `sessions/{id}/orders` · `sales-details` · `closing-control-report` · `boot` | tidak |
| POS sessions | `POST point-of-sale/registers/{id}/discard`, `configs/{id}/session` (buka), `sessions/{id}/opening-control` · `closing-control` · `close` · `cash-movements` · `cancel-draft-orders` | tidak |
| POS orders | `POST point-of-sale/orders/sync`; `GET point-of-sale/orders` (**riwayat**: filter `q`, `state`, `session_id`, `from`, `to`, paginasi); `GET orders/{id}/refundable-lines` · `receipt` · `invoice-pdf`; `POST orders/{id}/refund` · `invoice` | tidak |
| Employees | full CRUD `employees/employees` (payload `categories`, + restore/force) | ya |
| Departments | full CRUD `employees/departments` (+ restore/force, `complete_name`/`parent_path` otomatis) | ya |
| Job positions | full CRUD `employees/job-positions` (+ restore/force) | ya |
| Employment types | full CRUD `employees/employment-types` | tidak |
| Employee categories | full CRUD `employees/categories` | tidak |
| Work locations | full CRUD `employees/work-locations` (+ restore/force) | ya |
| Departure reasons | full CRUD `employees/departure-reasons` | tidak |
| Skill types | full CRUD `employees/skill-types` (+ restore/force) | ya |
| Skills | `GET employees/skills` (+ `/{id}`), read-only | ya |
| Skill levels | `GET employees/skill-levels` (+ `/{id}`), read-only | ya |
| Employee skills | full CRUD `employees/employee-skills` (+ restore/force) | ya |

## 6. Parameter Query (list)

| Param | Contoh | Keterangan |
|---|---|---|
| `filter[field]` | `filter[name]=acme` | Partial (LIKE) / exact sesuai field |
| `filter[field]` (list) | `filter[id]=1,2,3` | Exact multi-nilai (IN) |
| `filter[trashed]` | `with` \| `only` | Untuk resource soft-delete |
| `sort` | `sort=-name,id` | `-` = descending; hanya field yang diizinkan |
| `include` | `include=country,tags` | Relasi; boleh bersarang (`values.attributeOption`) |
| `page` / `per_page` | `page=2&per_page=50` | Default 15, maks 100 |

Nama `include` mengikuti Laravel dan dinormalisasi otomatis
(`bankAccounts` ⇄ `bank_accounts`, `uomPO` ⇄ `uom_po`, `values.attributeOption` ⇄ `values.attribute_option`).

## 7. Format Respons

**List** (mirip paginator Laravel):

```json
{
  "data": [ { "id": 1, "name": "USD" } ],
  "links": { "first": "...", "last": "...", "prev": null, "next": "..." },
  "meta": { "current_page": 1, "from": 1, "last_page": 85, "path": "...",
            "per_page": 2, "to": 2, "total": 169 }
}
```

**Single / write**: `{ "data": { ... }, "message": "Partner created successfully." }`
**Delete / force**: `{ "message": "Partner deleted successfully." }`

**Error**:

```json
401 { "message": "Unauthenticated." }
403 { "message": "This action is unauthorized." }
404 { "message": "Resource not found." }
422 { "message": "The given data was invalid.",
      "errors": { "name": ["The name field is required."] } }
400 { "message": "The include [x] is not allowed." }
429 { "message": "Too many login attempts. Please try again in 60 seconds." }
```

## 8. Otorisasi & Scope (parity Laravel)

- **Permission**: ability → `{ability}_{permission}` (mis. `view_any_partner_partner`,
  `create_support_currency`, `force_delete_product_product`). Diambil dari role
  (`model_has_roles` → `role_has_permissions`) **plus** permission langsung
  (`model_has_permissions`). Role `super_admin` = bypass.
- **Resource bersarang** memakai izin **induk**: index/show = `view_*` induk;
  create/update/delete/restore/force = `update_*` induk (khusus delete currency-rate = `delete_support_currency`).
  Pengecualian: **variants** memakai `product_product` sendiri (sesuai controller asal).
- **Company scope** (model ber-`company_id`): `company_id IN (active) OR company_id IS NULL`.
  Aktif = `default_company_id` bila diizinkan, jika tidak perusahaan pertama di `user_allowed_companies`.
  Di-skip untuk `super_admin`.
- **Ownership scope**: hanya model yang di Laravel memang global (`User`, `Team`),
  berdasarkan `users.resource_permission`: `global` (tanpa filter), `group` (anggota tim),
  `individual` (dirinya sendiri). Contoh: user `individual` akan melihat `include=creator`
  menjadi `null` untuk partner milik user lain.
- **Soft delete**: semua query default mengecualikan `deleted_at IS NOT NULL`.

## 9. Logika yang diport (bukan hanya CRUD)

- **Product variants**: `POST .../products/{id}/variants` menjalankan `generateVariants()` —
  kombinasi kartesian attribute/value, `name = "<product> - S / M"`,
  `price = product.price + Σ extra_price`, sinkronisasi `products_product_combinations`,
  arsip varian tak terpakai, set `is_configurable = true`.
- **Product tags**: payload `tags: [..]` disinkronkan ke pivot `products_product_tag`.
- **Price lists**: payload `items: [..]` divalidasi (nested rules) lalu menggantikan seluruh item;
  `PriceRuleItem` menghitung `apply_to`/`base`/`company_id`/`currency_id` otomatis.
- **Product attributes**: payload `options: [..]` membuat/mengganti baris
  `products_product_attribute_values`.
- **Addresses**: dipaksa `account_type='address'`, `parent_id` = partner dari URL.
- **Categories**: `parent_path` & `full_name` dihitung otomatis + proteksi circular reference.
- **Usage guard**: UOM tidak bisa dihapus bila dipakai produk; product attribute/varian diblokir
  bila produk "in use" (`UsageRegistry` memindai kolom `product_id`/`*_product_id` tabel modul lain).
- **Barcode** unik per perusahaan (lintas produk/packaging, sadar soft-delete).
- **Sales orders**: nomor otomatis dari tabel `sequences` (`sales.order`, prefix `SO/`), sinkronisasi
  `lines` (upsert + hapus yang tidak dikirim + pivot pajak), sinkronisasi `sales_order_tags`, dan
  **kalkulasi total** (`amount_untaxed`, `amount_tax`, `amount_total`, `price_subtotal/tax/total`,
  `qty_to_invoice`, `invoice_status`) mengikuti `OrderCalculator` Laravel. Termasuk transisi status
  draft → sale → cancel → quotation dan toggle lock.
- **Purchase orders**: nomor otomatis (`purchases.order`, prefix `PO/`), sinkronisasi `lines`
  (upsert + hapus yang tak dikirim + pivot pajak `purchases_order_line_taxes`), dan **kalkulasi total**
  (`untaxed_amount`, `tax_amount`, `total_amount`, `total_cc_amount`, `qty_to_invoice`,
  `invoice_status`, `receipt_status`) mengikuti `OrderCalculator` plugin Purchases. Aksi:
  confirm (`purchase`), cancel, draft, toggle-lock (`done` ⇄ `purchase`), confirm-receipt-date.
- **Inventories**: warehouse baru otomatis membuat lokasi **View** + **Stock** (dan mengisi
  `view_location_id`/`lot_stock_location_id`); lokasi menghitung `full_name`, `parent_path`
  (menyertakan id sendiri) dan mewarisi `warehouse_id` dari parent. Kuantitas menghitung
  `available_quantity = quantity - reserved`, serta `difference_quantity`/`inventory_diff_quantity`
  saat `counted_quantity` diisi; `apply` memindahkan hasil hitung ke `quantity`, `clear` mereset.
- **Accounts**: master data GL (chart of accounts, journals, taxes, tax groups, payment terms +
  due terms, incoterms, fiscal positions, cash roundings) dengan enum validasi sesuai Laravel
  (`AccountType`, `JournalType`, `TypeTaxUse`, `AmountType`, `DueTermValue`, `DelayType`,
  `RoundingStrategy`, `RoundingMethod`). Dokumen (invoice/bill/credit-note/refund) disajikan
  read-only dari `accounts_account_moves` difilter per `move_type`.
- **Projects**: proyek & task dengan sinkronisasi pivot (`projects_project_tag`, `projects_task_tag`,
  `projects_task_users`), stage per proyek/task, milestone, dan filter/sort/include standar.
  Enum validasi sesuai sumber (`ProjectVisibility: private|internal|public`,
  `TaskState: in_progress|change_requested|approved|cancelled|done`).
- **Timesheets**: di sumber, timesheet **bukan** tabel `timesheets_*` melainkan
  `Webkul\Project\Models\Timesheet` yang mewarisi `Webkul\Analytic\Models\Record` →
  tabel **`analytic_records`** (+ kolom `project_id`/`task_id` dari migrasi plugin Projects).
  Plugin `timesheets` sendiri hanya subclass + Filament, **tanpa REST API**, jadi endpoint
  `projects/timesheets` juga definisi kami mengikuti `RecordResource` V1. Yang penting: setiap
  create/update/delete meniru hook `boot()` sumber → menghitung ulang `total_hours_spent`,
  `effective_hours`, `overtime`, `remaining_hours`, `progress` pada task terkait **dan rollup ke
  task induk** (`subtask_effective_hours`). Permission mengikuti `TimesheetPolicy` sumber
  (`project_timesheet`).
- **Manufacturing**: plugin-nya juga **tanpa REST API** (hanya Filament + policy), jadi 9 endpoint
  `manufacturing/*` adalah definisi kami dengan nama permission asli dari sumber
  (`manufacturing_work::center`, `manufacturing_operation`, `manufacturing_bills::of::material`,
  `manufacturing_manufacturing::order`, `manufacturing_work::order`). Yang diport: work centers
  (+ kapasitas), operations, bills of materials (+ **baris BoM sebagai resource bersarang**
  `…/bills-of-materials/{id}/lines`), manufacturing orders (nomor otomatis `MO/00001` dari tabel
  `sequences`), work orders (nama default dari operasinya), loss types, dan productivity losses.
  Default mengikuti kolom NOT NULL sumber (BoM: `type`, `ready_to_produce`, `consumption`,
  `quantity`, `uom_id`; MO: `operation_type_id`, `source_location_id`, `destination_location_id`,
  `company_id` — sisanya diberi nilai default seperti `state=draft`).
- **Website & Blog**: sumber juga **tanpa REST API** (Website hanya punya `PageResource`, Blogs
  punya policy + resource V1 tapi tanpa route), jadi endpoint `website/pages`, `blogs/posts`,
  `blogs/categories`, dan `blogs/tags` adalah definisi kami dengan nama permission asli
  (`website_page`, `blog_post`, `blog_category`, `blog_tag`). Kolom **multi-bahasa**
  (`spatie/laravel-translatable`) — `title`/`content`/`meta_*` pada halaman, `name`/`sub_title`
  pada kategori, dst. — disimpan sebagai JSON; API mengembalikan **string sesuai locale**
  (default `en`, atau `?locale=id`) dan menerima string (ditulis pada locale aktif tanpa
  menghapus bahasa lain) maupun objek `{"en": "…", "id": "…"}`.
- **Recruitments**: plugin di sumber juga **tanpa REST API** (21 model, hanya Filament + policy),
  jadi 7 endpoint `recruitments/*` adalah definisi kami dengan nama permission asli
  (`recruitment_applicant`, `recruitment_candidate`, `recruitment_stage`, `recruitment_degree`,
  `recruitment_applicant::category`, `recruitment_refuse::reason`). Yang diport: applicant
  (+ pivot `categories` & interviewer), candidate (+ pivot `categories`, relasi skills),
  candidate-skills, stages (legend default `Blocked/Done/In Progress`), categories, degrees,
  dan refuse reasons. Relasi memakai modul yang sudah ada (job position, department, employee,
  skill/level/type).
- **Time-off**: plugin tanpa REST API, jadi 6 endpoint `time-off/*` adalah definisi kami dengan
  permission asli (`time_off_leave::type`, `time_off_allocation`, `time_off_time::off`,
  `time_off_accrual::plan`, `time_off_mandatory::day`). Yang diport: leave types, leaves, leave
  allocations, accrual plans & levels, dan mandatory days. Nilai enum DB divalidasi (mis.
  `start_type` = months/days/years, `frequency` = hourly…yearly) sehingga input salah → 422,
  bukan error DB.
- **Maintenance**: plugin tanpa REST API; 5 endpoint `maintenance/*` definisi kami dengan permission
  asli (`maintenance_equipment`, `maintenance_equipment::category`, `maintenance_maintenance::request`,
  `maintenance_stage`, `maintenance_team`). Teams menyimpan anggota lewat payload `users`
  (pivot `maintenance_team_users`). Stage/equipment/request mendukung soft delete.
- **System (plugin-manager & table-views)**: `system/plugins` memakai permission asli
  `plugin_manager_plugin`, dependensi plugin disimpan lewat payload `dependency_ids`
  (pivot `plugin_dependencies`). `system/table-views` & `system/table-view-favorites` **tidak punya
  permission di sumber** (plugin tanpa policy), jadi registry memakai `permission => null` dan
  ResourceController hanya menuntut autentikasi untuk resource tersebut. Kolom `filters` (JSON)
  ditulis/dibaca sebagai objek; `user_id` diisi otomatis dari token bila kosong.
- **Accounts — posting jurnal**: `POST accounts/invoices/{id}/post` dan `accounts/bills/{id}/post`
  (port perilaku `Account` facade) menjalankan `components/AccountPoster.php` lewat
  `controllers/v1/AccountMoveController.php`: hanya entry `draft` yang bisa diposting, akun baris
  diresolusi (produk `property_account_income_id`/`expense_id` → akun default jurnal → fallback per
  `account_type`), debit/credit baris yang masih 0 diisi sesuai arah dokumen, selisih pajak/pembulatan
  ditutup **satu baris lawan** ke akun piutang/utang, nomor diisi dari `sequences` bila kosong, dan
  `amount_*` dihitung ulang. `POST {id}/reset-to-draft` mengembalikan ke draft; posting ulang setelah
  reset memakai ulang baris lawan (tidak menumpuk). Lihat deviasi no. 71–72 untuk batasannya.
- **Chatter**: plugin chatter hanya punya model + panel Livewire (Resource V1 ada tapi **tidak
  dirutekan**), jadi `chatter/{messages,followers,attachments}` adalah definisi project ini dan —
  seperti table-views — memakai `permission => null` (hanya butuh autentikasi). Pesan/follower
  bersifat polymorphic: kirim morf class sumber (`Webkul\\Sale\\Models\\Order`) + id record.
  `causer_type`/`causer_id` diisi otomatis dari token, `followed_at` diisi otomatis, dan follower
  duplikat (unique `followable_type+followable_id+partner_id`) dikembalikan sebagai `422`.
- **Inventories (transfers)**: operasi gudang (receipts/deliveries/internal-transfers/dropships)
  menyediakan workflow `check-availability` → `todo` → `validate` → `cancel` → `return` lewat
  `components/TransferWorkflow.php` (port `InventoryManager`/`TransferWorkflow` Laravel). Lihat
  deviasi no. 62–63 untuk detail perilaku stok dan keterbatasannya.
- **Point of Sale**: modul ini di Laravel bersifat **workflow**, bukan CRUD resource — jadi
  diport sebagai `PointOfSaleController` + `PosOrderService` dengan route manual
  (`config/routes.php`), bukan lewat registry. Cakupan sekarang **lengkap**: register/katalog,
  lifecycle sesi (buka → opening-control → closing-control → close, `discard`, `cash-movements`),
  `orders/sync` (upsert order + lines + taxes + payments, nomor `POS/xxxxx` dari `sequences`,
  pembayaran kembalian, update total sesi), `refundable-lines` & `refund` (usulan refund),
  `invoice` (+ `invoice-pdf` HTML), `receipt`, `boot`, `cancel-draft-orders`, plus
  `sales-details` dan `closing-control-report`.
  **Prasyarat stok POS**: butuh `pos_configs` yang menunjuk `warehouse_id` + `operation_type_id`
  (dan `return_operation_type_id` untuk refund). Di DB saat ini gudang **sudah ada**
  (`Your Company` id 1, lokasi stok `WH/Stock` id 12) dan tipe operasi POS pun sudah ada
  (`PoS Orders` id 11 outgoing Stock→Customers, `PoS Returns` id 10 incoming Customers→Stock),
  tetapi belum ada baris `pos_configs` sehingga register POS belum terpetakan ke gudang.
  **Data POS sudah disiapkan di DB (siap dipakai):**
  - Register `Main POS` (`POS-MAIN`, id 1) — gudang `Your Company` (id 1), operasi
    `PoS Orders`/`PoS Returns`, journal kas `CASH` (6), journal invoice `INV` (1), akun piutang
    `101300 Account Receivable (PoS)` (43).
  - Metode bayar `Cash` (kas, journal CASH) dan `Card` (bank, journal BANK) — keduanya
    terhubung ke register, jadi pembayaran + kembalian bisa dihitung.
  - Kategori POS `Coffee` & `Pastry`; produk storable `Espresso` (3.50), `Kopi Susu` (4.25),
    `Croissant` (3.75), `Air Mineral` (1.50) — masing-masing stok awal **100** di `WH/Stock`.
  Mulai memakai: `POST /admin/api/v1/point-of-sale/configs/1/session` untuk membuka sesi, lalu
  `POST /admin/api/v1/point-of-sale/orders/sync` untuk transaksi. Halaman Astro `/pos`
  menampilkan register ini secara langsung.
- **Employees (HR)**: plugin Employees di Laravel **tidak punya REST API** (hanya Filament +
  policy), jadi endpoint `employees/*` didefinisikan sendiri mengikuti pola registry dan
  **bentuk respons class `*Resource` V1 sumber** (Employee, Department, WorkLocation,
  DepartureReason). 11 resource: `employees`, `departments` (complete_name/parent_path dihitung
  otomatis + guard circular reference), `job-positions`, `employment-types`, `categories`
  (pivot `employees_employee_categories`), `work-locations`, `departure-reasons`, `skill-types`,
  `skills` & `skill-levels` (read-only), dan `employee-skills`.

## 10. Integrasi Astro

Origin CORS diatur di `config/params.php` (default `http://localhost:4321`) atau env
`AUREUS_CORS_ORIGINS` (dipisah koma). Preflight `OPTIONS` dijawab `204`.

```ts
const API = import.meta.env.PUBLIC_API_URL; // mis. http://localhost:8899/admin/api/v1

const res = await fetch(`${API}/login`, {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({ email, password }),
});
const { token } = await res.json();

const partners = await fetch(`${API}/partners/partners?include=country,state&per_page=20`, {
  headers: { Authorization: `Bearer ${token}` },
}).then((r) => r.json());
```

## 11. Catatan Parity & Perbedaan yang Disengaja

1. `initSQLs` tidak ada di Yii 2.0.55 → timezone via PDO init command.
2. **Izin `product_tag` tidak ada di database** (hanya `partner_tag`, `project_tag`, dsb.),
   jadi `/products/tags` mengembalikan **403** — sama seperti Laravel aslinya (TagPolicy plugin
   Products menunjuk permission yang tidak pernah di-generate). Perbaikan ada di sisi data Laravel.
3. Typo izin di `BankPolicy`/`UOMPolicy` (mis. `view_any_supporbank`) → port ini memakai nama
   izin yang benar-benar ada di DB (`support_bank`, `support_u::o::m::category`).
4. `uom_id`/`uom_po_id` pada Product dibuat **wajib saat create** (kolom DB `NOT NULL`).
5. Pesan error dibuat menyerupai Laravel; status code & bentuk body identik.
6. Timestamp ISO-8601 UTC (`...T02:34:45.000000Z`).
7. `include` juga didukung di endpoint single (show/store/update).
8. `company_id IN (...) OR IS NULL` diterapkan lewat sub-query, bukan global scope Eloquent.
9. **Sales**: modul `inventories` & `accounts` belum diport, sehingga efek samping seperti
   pembuatan delivery (procurement) dan invoice otomatis tidak dijalankan. `delivery_status`
   di-set `no` (sama seperti perilaku sumber ketika plugin inventories tidak terpasang), dan
   `qty_invoiced` = 0. Perhitungan pajak disederhanakan ke tarif persen/fixed dari `accounts_taxes`.
10. **Sales**: `price_unit` yang tidak dikirim memakai `products.price` (sumber memakai
   `PriceListResolver`; price list bertingkat belum dihitung). `warehouse_id`/`route_id` disimpan
    tetapi tidak diekspos sebagai include (model gudang belum diport).
11. **Sales**: endpoint `deliveries`/`invoices` bersifat read-only dengan field ringkas
    (bukan `OperationResource`/`MoveResource` penuh).
12. **Purchases**: `receipt_status` di-set `no` dan `qty_received` dari stock move = 0 karena modul
    inventories belum diport (sesuai perilaku sumber saat plugin tidak terpasang). `qty_received`
    manual tetap dihitung. `invoice_status` = `to_invoiced`/`invoiced` hanya dievaluasi pada state
    `purchase`/`done`.
13. **Purchases**: `confirm` langsung berakhir di state `purchase` (asumsi
    `enable_lock_confirmed_orders = false`); `toggle-lock` memindahkan `purchase` ⇄ `done`.
14. **Purchases**: purchase agreements bersifat **read-only** (`index`/`show`); create/update/
    confirm/close/cancel/restore menyusul. Endpoint receipts/bills memakai field ringkas.
15. **Purchases**: izin mengikuti nama di DB: `purchase_purchase::order`,
    `purchase_vendor::price`, `purchase_purchase::agreement`.
16. **Inventories**: transfer (receipts/deliveries/internal-transfers/dropships) bersifat
    **read-only**; workflow `check-availability`/`todo`/`validate`/`cancel`/`return` serta pembuatan
    operasi + moves belum diport.
17. **Inventories**: warehouse baru hanya membuat lokasi View+Stock (bukan pohon lokasi, rute, dan
    operation type lengkap seperti sumber) — sisanya dibuat manual lewat endpoint lokasi.
18. **Inventories**: `apply` kuantitas disederhanakan (tanpa membuat stock move penyesuaian);
    `clear` hanya mereset hitungan.
19. **Inventories**: path/full_name lokasi dihitung saat simpan; memindahkan parent tidak otomatis
    memperbarui seluruh descendant.
20. **Inventories**: izin `inventory_tag` tidak ada di DB → `GET /inventories/tags` = **403**
    (perilaku sama dengan Laravel aslinya). Modul `routes`, `rules`, `putaway-rules`, `scraps`,
    `order-points`, dan `inventories/products` belum diport.
21. **Accounts**: dokumen (invoices/bills/credit-notes/refunds) **read-only**; workflow
    `confirm`/`cancel`/`pay`/`reverse`/`reset-to-draft`/`set-as-checked`, posting jurnal,
    pembayaran, dan rekonsiliasi belum diport.
22. **Accounts**: tax CRUD mengisi field utama; **repartition lines** (`accounts_tax_partition_lines`)
    belum dibuat otomatis.
23. **Accounts**: `journal.invoice_reference_type`/`invoice_reference_model` diisi default
    (`none`/`odoo`) karena kolom NOT NULL namun tidak ada di request.
24. **Accounts**: belum diport — `categories`, `customers`, `vendors`, `products`,
    `products.variants`, `payment-methods`, `bank-statements`, `move-lines`, `payments`,
    `journal-entries`. Customers/vendors/products sudah tercakup modul Partners/Products.
25. **Projects**: tag & user pivots disinkronkan saat create/update; `projects_user_project_favorites`
    (favorit) dan kolom `tags` JSON pada project stage tidak diekspos; timesheet & activity plan
    belum diport.
26. **Projects**: soft-delete proyek **tidak** meng-cascade task/milestone, dan restore hanya
    mengembalikan proyek (bila sumber meng-cascade, ini perbedaan yang disengaja agar aman).
27. **Projects**: dependency task tidak punya endpoint (hanya flag `allow_task_dependencies`);
    `priority` task berupa boolean.
28. **Point of Sale**: seluruh route sumber sudah diport, namun arsitekturnya berbeda — Laravel
    memakai facade + ~40 service (`OrderWorkflow`, `OrderProcessor`, `RefundProcessor`, dll),
    Yii2 memakai `PointOfSaleController` + `PosOrderService` (bukan registry resource).
29. **Point of Sale**: `invoice-pdf` mengembalikan **HTML** (sumber: PDF dompdf), sedangkan
    `sales-details` dan `closing-control-report` mengembalikan **JSON** (sumber: PDF/stream) agar
    bisa langsung dipakai FE SSR.
30. **POS stok**: `orders/sync` membuat picking (`inventories_operations`) + `inventories_moves`
    + `inventories_move_lines` dan langsung menyesuaikan `inventories_product_quantities` di
    lokasi stok gudang config — tanpa route/putaway/reservasi. Hanya produk `is_storable = 1`
    yang diproses; `stock_update_mode = at_closing` menunda stok sampai sesi ditutup.
31. **POS invoice**: membuat satu `accounts_account_moves` bertipe `out_invoice` (piutang +
    pendapatan per baris) **tanpa** COGS/jurnal selisih kas. Butuh `invoice_journal_id` dan
    `receivable_account_id` pada config (bila kosong → 422).
32. **POS refund**: `refund` mengembalikan **usulan** baris refund (sama seperti sumber); order
    refund yang benar-benar tersimpan dibuat lewat `orders/sync` dengan `refunded_order_id` +
    baris ber-qty negatif.
33. **POS data (sudah disiapkan)**: DB asli sekarang berisi register `Main POS` (`POS-MAIN`,
    id 1), 2 metode bayar (`Cash`, `Card`), 2 kategori POS (`Coffee`, `Pastry`), dan 4 produk
    storable (`Espresso`, `Kopi Susu`, `Croissant`, `Air Mineral`) dengan stok awal 100 di
    `WH/Stock`, plus pivot config↔payment-method dan config↔kategori. Sesi & transaksi POS tetap
    kosong (dibuat pemakaian nyata). Modul sudah diuji end-to-end di DB asli
    (buka sesi → jual 2 Espresso + 1 Croissant → stok 100→98/99 → receipt → invoice), lalu data
    transaksi uji dihapus lagi sehingga tidak ada sisa.
34. **Employees**: sumber tidak menyediakan REST API untuk modul ini, jadi endpoint `employees/*`
    adalah **definisi kami** (bukan port 1:1). Yang dijaga agar tetap konsisten dengan sumber:
    nama field, cast (`is_active`/boolean, tanggal ISO), nama relasi, dan **nama permission**
    yang benar-benar ada di DB (`employee_employee`, `employee_department`, `employee_job::position`,
    `employee_employment::type`, `employee_employee::category`, `employee_work::location`,
    `employee_departure::reason`, `employee_skill::type`, `employee_employee::skill`).
35. **Employees**: `skills` dan `skill-levels` bersifat **read-only** (sumber tidak punya policy
    tersendiri — keduanya dikelola lewat *skill type*); permission yang dipakai untuk keduanya
    adalah `employee_skill::type` (yang terdekat yang tersedia di DB).
36. **Employees**: relasi ke modul yang belum diport hanya disajikan sebagai ID —
    `calendar_id` (Calendar), `bank_account_id` (Bank Account), `address_id` (Address),
    `industry_id` (Industry); `country_of_birth` hanya berupa kolom ID (nama relasi bentrok
    dengan kolomnya di sumber, jadi relasinya tidak diduplikasi).
37. **Employees (data seed)**: `employees_employees` berisi 14 baris dan **7 di antaranya sudah
    soft-deleted** (`deleted_at` terisi), jadi list default menampilkan 7 karyawan aktif —
    pakai `filter[trashed]=with|only` untuk melihat yang terhapus.
38. **Timesheets**: tabelnya adalah **`analytic_records`** (bukan `timesheets_*`); plugin
    `timesheets` di sumber hanya subclass + Filament tanpa REST API, sehingga endpoint
    `projects/timesheets` adalah definisi kami (field mengikuti `RecordResource` V1). Soft delete
    tidak dipakai (tabel tanpa `deleted_at`), dan filter rentang tanggal belum ada — pakai
    `filter[date]` untuk tanggal persis.
39. **Timesheets**: permission `view_any|view|create|update|delete_project_timesheet` **belum
    ter-seed** di DB asli karena plugin-nya tidak dipasang. Kami menambahkannya ke tabel
    `permissions` (guard `web`) dan memberikannya ke role `Admin`, supaya nama permission tetap
    sama dengan `TimesheetPolicy` di sumber.
40. **Timesheets (data demo)**: di DB asli ditambahkan project `Implementasi ERP` (stage `To Do`,
    `allow_timesheets`) dengan task `Setup server & konfigurasi` (16 jam) + subtask
    `Migrasi data master` (8 jam) dan task stage `Todo`, plus 4 timesheet (4h, 3.5h, 2.5h pada
    task induk; 5h pada subtask). Hasil hitung: task induk **total 15 jam** (10 sendiri + 5
    subtask), sisa 1 jam, progress 93.75%; subtask 5 jam, progress 62.5%.
41. **Manufacturing**: sumber juga **tidak punya REST API** (hanya Filament + policy), jadi 9
    endpoint `manufacturing/*` adalah definisi kami; nama permission tetap sama dengan sumber
    (`manufacturing_work::center`, `manufacturing_operation`, `manufacturing_bills::of::material`,
    `manufacturing_manufacturing::order`, `manufacturing_work::order`). Belum diport: unbuild
    orders, work center productivity logs & alternatives, batch productions, order splits,
    consumption warnings, dan `manufacturing_lot`/`product`/`transfer` (lot & transfer memakai
    tabel modul Inventories).
42. **Manufacturing**: baris BoM diekspos sebagai **resource bersarang**
    (`/manufacturing/bills-of-materials/{id}/lines`) — engine tidak mendukung include bertingkat
    (`lines.product`), jadi komponen diambil lewat endpoint bersarang itu.
43. **Manufacturing (data demo)**: di DB asli ditambahkan work center `Barista Station` (`WC-BAR`),
    BoM `BOM-ESPRESSO` (produk Espresso, normal, konsumsi flexible) dengan 2 komponen
    (Kopi Susu 0.2 · Air Mineral 0.25), operasi `Brewing & serving`, 1 manufacturing order
    (`MO/00001`, qty 20, state draft) dan 2 work order. Tabel loss types (4) & productivity
    losses (7) memang sudah berisi data seed.
44. **Website & Blog**: sumber tidak menyediakan REST API (Website hanya `PageResource`; Blogs
    punya policy + resource V1 tanpa route), jadi 4 endpoint `website/pages`, `blogs/posts`,
    `blogs/categories`, `blogs/tags` adalah definisi kami; nama permission mengikuti sumber
    (`website_page`, `blog_post`, `blog_category`, `blog_tag`).
45. **Kolom multi-bahasa**: `title`/`content`/`meta_*` (halaman & artikel), `sub_title`,
    `name`/`meta_*` (kategori) adalah JSON spatie-translatable. API mengembalikan **string sesuai
    locale** (default `en`; `?locale=id` untuk bahasa lain) dan menerima string (ditulis ke locale
    aktif, bahasa lain dipertahankan) atau objek multi-bahasa. Slug dibuat otomatis dari judul bila
    tidak dikirim.
46. **Perbaikan engine (penting)**: penulisan kolom JSON sebelumnya ter-encode **dua kali**
    (`JSON_TYPE = STRING`) karena `BaseModel::beforeSave` ikut `json_encode` padahal Yii sudah
    meng-encode kolom JSON — data seperti itu tidak bisa dibaca Laravel/spatie. Cabang tersebut
    dihapus, sehingga nilai array kini tersimpan sebagai objek JSON asli
    (`JSON_TYPE(title) = OBJECT`, terverifikasi) dan kompatibel dengan admin Laravel.
47. **Website & Blog**: restore/force delete hanya tersedia permission-nya untuk `blog_post`;
    untuk `blogs/categories` dan `blogs/tags` permission restore/force tidak ada di DB, jadi route
    restore/force keduanya akan 403 (soft delete tetap berjalan) — mengikuti pola `product_tag`
    yang juga tidak ada di DB.
48. **Recruitments**: sumber tidak punya REST API, jadi endpoint `recruitments/*` adalah definisi
    kami. `candidate-skills` memakai permission `recruitment_candidate` (tidak ada permission
    khusus candidate-skill di DB). Pivot **`recruitments_stages_jobs`** (stage ↔ job position)
    diekspos lewat payload `job_position_ids` pada `recruitments/stages` + include `jobs`, dan
    `recruitments_job_position_interviewers` lewat payload `interviewer_ids` pada
    `employees/job-positions` + include `interviewers` (relasi baca juga `stages`).
    `applicant_interviewers` masih read-only (belum ada payload-nya).
49. **Recruitments**: activity plans/activity types dan UTM source/medium (`recruitment_u::t::m::*`)
    belum diport — applicant hanya menyimpan `source_id`/`medium_id` sebagai angka.
50. **Recruitments (data demo)**: di DB asli ditambahkan 1 candidate (`Dewi Lestari`, Bachelor, IT,
    +1 skill) dan 1 applicant (stage *First Interview*, job Software Engineer, dept R&D) agar
    halaman `/recruitments/*` terisi; master data seed (6 stages, 4 categories, 4 degrees,
    6 refuse reasons) sudah ada.
51. **Time-off**: sumber tidak punya REST API; endpoint `time-off/*` adalah definisi kami. Pivot
    `time_off_user_leave_types` dan endpoint "my allocation / my time off" belum diport (UI hanya
    memakai 6 resource utama).
52. **Time-off**: approval workflow (validate_one/validate_two/refuse, alokasi otomatis, accrual
    scheduler) belum dijalankan — API menyimpan `state` dan field terkait, tetapi tidak mengubah
    saldo cuti secara otomatis.
53. **Time-off (data demo)**: di DB asli ditambahkan 1 pengajuan cuti (Emily Davis, *Paid Time
    Off*, 3 hari, state `confirm`) dan 1 alokasi 12 hari untuk 2026; tipe cuti (6), mandatory days
    (2), dan accrual plan seed sudah ada.
54. **Maintenance**: sumber tidak punya REST API; 5 endpoint `maintenance/*` adalah definisi kami.
    Preventive/recurring scheduler & MTBF calculation belum dijalankan (hanya menyimpan field
    `recurring_maintenance`, `repeat_*`, `expected_mtbf`); instruction PDF/google slide disimpan
    sebagai string.
55. **Maintenance (data demo)**: di DB asli ditambahkan kategori `IT Equipment`, equipment
    `Server Rack A` (SN-001, Ruang Server), dan request `Ganti fan server` (stage *In Progress*,
    tim *Internal Maintenance*, prioritas high); stage seed (4) dan tim seed (1) sudah ada.
56. **System — table-views tanpa permission**: `table_views`/`table_view_favorites` tidak memiliki
    permission Spatie di sumber; agar tetap bisa diakses, ResourceController mengizinkan `permission
    => null` (hanya butuh token). Ini penyimpangan terkontrol dan hanya dipakai oleh 2 resource itu.
57. **System — plugin manager**: plugin di DB adalah katalog (20 baris seed: accounting, accounts,
    barcode, blogs, contacts, …). Aksi upload/uninstall/activate nyata (filesystem/composer) belum
    dijalankan; API hanya mengubah kolom `is_active`/`is_installed`/`sort` + dependensi.
58. **System (data demo)**: di DB asli ditambahkan 1 table view `Cuti menunggu approval`
    (filter `{"state":"confirm"}`, publik) dan 1 favorite `time-off/leaves` untuk admin.
59. **Sales/Purchases — create invoice & bill**: sumber hanya menyediakan daftar invoice/bill
    (`GET .../invoices`, `.../bills`); pembuatannya adalah aksi UI Filament
    (`CreateInvoiceAction`/`CreateBillAction`) yang memanggil
    `Sale\Services\Invoicer` dan `Purchase\Services\Biller`. Keduanya diport sebagai
    `components/SalesInvoicer.php` dan `components/PurchaseBiller.php` dan diekspos lewat
    `POST sales/orders/{id}/invoice` serta `POST purchases/purchase-orders/{id}/bill`
    (endpoint tambahan milik proyek ini).
60. **Sales/Purchases — deviasi invoice/bill**: (a) nomor dokumen dibuat lokal
    (`INV/YYYY/NNNNN`, `BILL/YYYY/NNNNN`) karena tabel `ir_sequence` tidak diport;
    (b) `debit`/`credit` move line masih 0 — amount diisi tanpa resolusi akun (posting jurnal
    menyusul bersama modul Accounts); (c) invoice sales memakai kuantitas dari `qty_to_invoice`
    (policy produk `order`/`delivery`), sama seperti sumber yang bergantung pada `qty_delivered`;
    (d) bill pembelian memakai **kuantitas dipesan − sudah ditagih** karena receipt/stock move
    belum diport (sumber memakai `qty_received`); (e) `qty_invoiced` order line kini dihitung
    dari pivot invoice/bill sehingga pembuatan invoice/bill ganda otomatis ditolak (422).
61. **Sales/Purchases (data demo)**: DB asli berisi `SO/00001` (partner *My Company*, 1 baris,
    diskon 10%, sudah dikonfirmasi) + faktur `INV/2026/00001` (180), dan `PO/00001` (sudah
    dikonfirmasi) + tagihan `BILL/2026/00001` (150).
62. **Inventory transfer workflow**: sumber memakai `OperationController` + `InventoryManager`
    (`TransferWorkflow`). Yang diport: `POST inventories/{receipts|deliveries|internal-transfers|
    dropships}/{id}/{check-availability|todo|validate|cancel|return}` lewat
    `components/TransferWorkflow.php` + `controllers/v1/InventoryOperationController.php`
    (permission `update_inventory_*` yang sudah ada). Resource ini sebelumnya read-only
    (`actions => index, view`) — aksi workflow kini didaftarkan di `actions` + `extraRoutes`.
63. **Inventory transfer — deviasi**: (a) MoveConfirmer/Reserver/Completer/Canceller digabung ke
    `TransferWorkflow`; (b) stok diposting seperti POS (`inventories_moves` + `inventories_move_lines`
    + `inventories_product_quantities`) tanpa picking/route/putaway/back-order; (c) `reserve`
    menghitung kuantitas tersedia = `quantity - reserved_quantity` pada lokasi sumber dan menandai
    move `assigned`/`partially_assigned`; (d) `validate` memindahkan stok sumber → tujuan, mengisi
    `closed_at`, dan memurnikan baris kuantitas nol; (e) `return` membuat operasi draft baru dengan
    lokasi terbalik dan nama `RET/YYYY/NNNNN` (tabel `sequences`); (f) chatter/event
    (OperationDone/Canceled/Returned) dan cross-link pesan belum dijalankan.
64. **Inventory transfer — UI**: tombol workflow ada di `/inventory/transfers` (tab Receipts /
    Deliveries / Internal Transfers / Dropships) per baris: **Todo** (draft), **Cek stok**,
    **Validasi**, **Batalkan** (state terbuka), dan **Retur** (state done). Semua memanggil
    `/api/action` seperti aksi order.
65. **Inventory transfer (data demo)**: DB asli berisi `WH/INT/00001` (selesai, + retur
    `RET/00001` draft), `WH/INT/00002` (dibatalkan), `WH/INT/00003` (draft) untuk product 5
    (Espresso) qty 2 dari *Stock* → *Quality Control*. Efek stok: WH/Stock **100 → 98**,
    Quality Control **0 → 2** (terlihat di halaman `/inventory/stock`).
66. **Chatter**: sumber tidak punya REST API (hanya model + Livewire panel) dan tidak punya
    permission Spatie → 3 resource `chatter/*` memakai `permission => null`. Relasi polymorphic
    (`messageable_*`, `followable_*`) dikirim sebagai morf class apa adanya; tidak ada resolusi
    otomatis ke model tujuan (tidak ada `include` ke record induk).
67. **Chatter — upload lampiran belum diport**: `chatter/attachments` hanya menyimpan metadata
    (`file_path`, `original_file_name`, `mime_type`, `file_size`); endpoint multipart, penyimpanan
    file, aksesor `url`, notifikasi/email follower, dan perhitungan mention belum dijalankan.
68. **Chatter (data demo)**: DB asli berisi 1 catatan pada `SO/00001`
    (*Konfirmasi ke pelanggan*, type `comment`), 1 lampiran `INV-2026-00001.pdf` (metadata), dan
    1 follower partner *My Company* untuk order tersebut.
69. **Recruitments — pivot stage/job & interviewer**: `PATCH recruitments/stages/{id}` dengan
    `job_position_ids` dan `POST/PATCH employees/job-positions/{id}` dengan `interviewer_ids`
    mengganti isi pivot (replace, bukan append). Relasi `viaTable` **wajib** didaftarkan juga di
    `apiRelations()` model — kalau hanya getter-nya dibuat, `include=` akan mengembalikan array
    kosong tanpa error (kasus ini sudah diperbaiki untuk `jobs`, `interviewers`, dan `stages`).
70. **Recruitments (data demo lanjutan)**: job position 1 di DB asli kini punya 2 interviewer
    (Admin, Admin Dua); pivot `recruitments_stages_jobs` (10 baris) adalah seed sumber dan tidak
    diubah.
71. **Accounts — posting jurnal (baru)**: `components/AccountPoster.php` +
    `controllers/v1/AccountMoveController.php`, aktif di resource `invoice` dan `bill`
    (`POST {id}/post`, `POST {id}/reset-to-draft`, include tambahan `lines`). Deviation 60(b) yang
    menyebut "debit/credit masih 0" kini hanya berlaku sampai entry diposting — **setelah**
    posting, baris punya akun + debit/credit dan jumlahnya balance.
72. **Accounts — deviasi posting**: (a) akun diresolusi sederhana (tanpa fiscal position/analytic);
    (b) pajak tidak dibuat sebagai baris akun pajak terpisah — selisih pajak ikut ditutup oleh baris
    lawan piutang/utang; (c) rekonsiliasi pembayaran, lock date, dan COGS/stok otomatis belum diport;
    (d) UI Astro belum punya tombol post (endpoint API dulu).
73. **Accounts (data demo)**: DB asli berisi `INV/2026/00001` (draft) dan `BILL/2026/00001` (draft)
    dari modul Sales/Purchases — keduanya belum diposting; uji posting dilakukan di DB klon
    (`aureuserp_acc_test`, sudah dihapus) dengan hasil balance 180/180 dan 150/150.
74. **POS — layar kasir (UI)**: `catalog` sekarang ikut mengembalikan `payment_methods`
    (`id`, `name`, `is_cash_count`) agar UI bisa merender metode bayar. Endpoint baru di sisi Astro:
    `POST /api/pos/checkout` (meneruskan ke `orders/sync`, token tetap server-side).
75. **POS (data demo)**: sesi kasir `#1` (register `Main POS`) dibuka dan transaksi uji
    `POS/00001` tersimpan (state `paid`, total 7.00, dibayar 12.00, product 3 qty 2) beserta
    operasi stok `id 11`; stok product 3 di WH/Stock turun **100 → 98**. Data ini nyata dan
    dipakai untuk memverifikasi layar kasir end-to-end.

## 12. Struktur Proyek

```
aureuserp-yii2/
├─ config/
│  ├─ db.php            # koneksi DB (env-driven: AUREUS_DB, default "aureuserp")
│  ├─ web.php           # app API-only (JSON, pretty URL, CORS, error handler, strict parsing)
│  ├─ console.php       # app console (dipakai script uji / pembuatan token)
│  ├─ resources.php     # REGISTRY resource (sumber kebenaran endpoint)
│  └─ routes.php        # URL rules dibangun otomatis dari resources.php + route POS
├─ components/
│  ├─ BaseModel.php           # serialisasi fields/casts/relations, DSL validasi, soft delete, timestamps
│  ├─ ScopedQuery.php         # soft delete / company scope / ownership scope
│  ├─ Access.php              # permission (Spatie) + allowed/active company
│  ├─ ResourceController.php  # CRUD generik + filter/sort/include/pagination/pivot + gerbang `actions`
│  ├─ Resources.php, ApiController.php, BearerAuth.php, ApiErrorHandler.php, ValidationException.php
│  ├─ SequenceService.php     # nomor dokumen (sequences) — dipakai order, returns, dll.
│  ├─ SalesCalculator.php / PurchaseCalculator.php     # recompute total & status (OrderCalculator)
│  ├─ SalesInvoicer.php / PurchaseBiller.php           # create invoice/bill dari order (Invoicer/Biller)
│  ├─ TransferWorkflow.php    # confirm/reserve/complete/cancel/return operasi gudang
│  ├─ PosOrderService.php     # workflow POS lengkap (order, bayar, stok, invoice, sesi)
│  ├─ UsageRegistry.php       # pemindaian "product in use"
│  ├─ Validators/             # exists, unique, barcode, company-allowed, array
│  └─ hooks/                  # CategoryHook, DepartmentHook, TranslatableHook
├─ models/               # ±70 model ActiveRecord (1 model per tabel utama)
└─ controllers/v1/       # ResourceController generik + Auth, Error, Order, PurchaseOrder,
                         #   InventoryOperation, PointOfSale, Variant, PriceList, ProductAttribute, Quantity
```

## 13. Status & Modul yang Sudah Diport

Teruji end-to-end: login/logout (email **atau** username), CRUD, include (termasuk nested), filter/sort/
pagination, soft delete/restore/force, `401/403/404/422/429`, CORS preflight, serta permission & scope
(company + ownership).

| Modul | Resource utama | Permission (contoh) | Halaman Astro |
|---|---|---|---|
| Security/Support/Partners | users, roles, permissions, countries, states, currencies, uoms, activities | `user`, `role`, `support_country` | `/master/*`, `/partners` |
| Products | products, variants, attributes, categories, packagings, price-lists, uoms | `product`, `product_attribute` | `/products` |
| Sales | orders (+lines/tags), tags, invoices (`GET`+`POST invoice`) | `sale_order`, `sale_tag` | `/sales/orders`, form + workflow |
| Purchases | purchase-orders (+lines), vendor price lists, bills (`GET`+`POST bill`) | `purchase_purchase::order` | `/purchases/orders`, form + workflow |
| Inventories | warehouses, locations, operation types, routes, rules, quantities, moves, receipts/deliveries/internal-transfers/dropships + workflow | `inventory_internal`, `inventory_receipt` | `/inventory/*` + tombol workflow |
| Accounts | accounts, journals, taxes, tax groups, payment terms, incoterms, fiscal positions, cash roundings | `account_account`, `account_journal` | `/accounts*` (read-only) |
| Projects & Timesheets | projects, tasks, stages, milestones, tags, timesheets (`analytic_records`) | `project`, `project_timesheet` | `/projects/*` |
| Manufacturing | work centers, capacities, operations, BoMs (+lines), orders, work orders, loss types, productivity losses | `manufacturing_*` | `/manufacturing/*` |
| Website & Blog | pages, posts, categories, tags (JSON multi-bahasa + `?locale=`) | `website_page`, `blog_post` | `/website/pages`, `/blogs/*` |
| Recruitments | applicants, candidates, stages, categories, degrees, refuse reasons, candidate skills | `recruitment_*` | `/recruitments/*`, `/manage/*` |
| Time-off | leaves, leave types, leave allocations, accrual plans/levels, mandatory days | `time_off_*` | `/time-off/*` |
| Maintenance | equipments, equipment categories, requests, stages, teams (+pivot anggota) | `maintenance_*` | `/maintenance/*` |
| System | plugins (+pivot dependensi), table views, table view favorites | `plugin_manager_plugin` (+2 tanpa permission) | `/system/*` |
| Chatter | messages, followers, attachments (polymorphic, tanpa permission sumber) | — (autentikasi saja) | `/chatter`, `/chatter/followers` |
| Point of Sale | register, config, sesi, order, payment, invoice/receipt | `pos_*` | `/pos*` |
| Employees/HRM | employees, departments (hierarki), job positions, employment types, work locations, departure reasons, skill types/skills/levels, employee skills | `employee`, `employee_department` | `/employees/*` |

Sisa yang belum diport ada di **§11** (mis. chatter, tabel residual Recruitments, posting/rekonsiliasi
Accounts, receipt/delivery yang menggerakkan stok mengikuti sumber).

## 14. Cara Menambah Modul / Resource Baru

1. **Cek sumber (lookup saja, jangan dijalankan)** di `../BACKUP/aureuserp-master/plugins/<vendor>/<plugin>/`:
   `src/Models/*` + `src/Http/Resources/V1/*` untuk bentuk field & cast, `routes/api.php` untuk endpoint,
   dan tabel `permissions` di DB untuk nama permission asli.
2. **Model** di `models/` (`extends app\components\BaseModel`):
   `tableName()`, `isSoftDelete()` bila ada `deleted_at`, relasi (`getX()`), lalu `apiFields()`,
   `apiCasts()` (`int|float|bool|iso|json`), `apiRelations()`, `apiRules()` (DSL: `required|string|max:255`,
   `integer`, `number`, `boolean`, `date`, `in`, `exists:tabel`, `company_allowed`, `unique:...`).
3. **Registry** di `config/resources.php` — inilah yang menentukan endpoint:

   | Key | Arti |
   |---|---|
   | `pattern` | URL (mis. `time-off/leaves` → `/admin/api/v1/time-off/leaves`) |
   | `model` | kelas model |
   | `controller` | `v1/<custom>` bila butuh controller sendiri (default `v1/resource`) |
   | `permission` | nama permission **asli** dari DB; ability otomatis jadi `<ability>_<permission>` |
   | `exact` / `partial` | kolom yang boleh difilter (`filter[x]=`) |
   | `sorts` | kolom `sort=` yang diizinkan |
   | `includes` / `defaultIncludes` / `writeIncludes` | relasi `include=`, default saat list, dan saat create/update |
   | `soft` | mengaktifkan `POST /{id}/restore` + `DELETE /{id}/force` |
   | `payload` + `pivots` | field non-kolom (nested lines, array id pivot): `['users' => 'array_of_int:users']` + tabel pivot `fk`/`ref` |
   | `baseQuery` / `baseWhere` | scope resource dari tabel yang sama (mis. receipts vs deliveries beda `operation_type.type`) |
   | `parentScope` + `auth => parent` | resource bersarang (`.../products/{id}/variants`) |
   | `actions` | daftar aksi yang diizinkan; **aksi di luar daftar ini → 404** |
   | `extraRoutes` | aksi tambahan: `['verb' => 'POST', 'pattern' => '<id:\d+>/confirm', 'action' => 'confirm']` |
   | `hooks` | mis. `CategoryHook`, `TranslatableHook` untuk kolom JSON multi-bahasa |
4. **Controller custom** (hanya bila perlu): `extends ResourceController`, tambahkan `verbs()` +
   `actionXxx($id)`, panggil `$this->authorize('update', $this->cfg())` dan `$this->findModel(...)`.
   Resource tanpa permission (konvensi sumber: plugin tanpa policy) → registry `'permission' => null`
   (hanya butuh autentikasi).
5. **Logika bisnis** ditaruh di `components/` sebagai service (port dari `Services/*` Laravel) supaya
   bisa dipakai controller mana pun dan mudah diuji — contoh: `SalesInvoicer`, `TransferWorkflow`.
6. **Perbarui dokumentasi**: tabel §5 (endpoint), §9 (logika yang diport), §11 (deviasi bernomor),
   §13 (modul), dan README Astro + `../TUTORIAL.md` bila ada halaman UI.
7. **Uji** dengan alur di §15, lalu pastikan `php -l` bersih dan jumlah resource/route bertambah wajar.

## 15. Perintah Operasional & Tips Uji

```powershell
# 1) lint semua file PHP project
$bad = 0; Get-ChildItem -Recurse -Filter *.php -Path config,components,controllers,models |
  ForEach-Object { php -l $_.FullName > $null; if ($LASTEXITCODE -ne 0) { $bad++; $_.Name } }
"lint failures: $bad"

# 2) hitung resource & route (registry → route otomatis)
php -r "define('YII_DEBUG',true); require 'vendor/autoload.php'; require 'vendor/yiisoft/yii2/Yii.php';
  new yii\console\Application(require 'config/console.php');
  echo count(require 'config/resources.php'), ' resources / ',
       count(require 'config/routes.php'), ' routes', PHP_EOL;"

# 3) jalankan API (default DB 'aureuserp'; set AUREUS_DB untuk DB lain)
php -S 0.0.0.0:8899 -t web web/index.php
```

**Uji di DB klon (wajib sebelum menyentuh DB asli):** buat klon `mysqldump iTrustERP | mysql aureuserp_<x>_test`,
set password user 1, jalankan API dengan `$env:AUREUS_DB='aureuserp_<x>_test'`, uji, lalu
`DROP DATABASE aureuserp_<x>_test` dan pastikan `personal_access_tokens` kembali 0.

Catatan penting yang sering bikin bingung:

- **Bearer token berformat `{id}|{plain}`** (Sanctum). Mengirim plain-nya saja → `401 Unauthenticated`.
- Token dibuat lewat console: `list($t,$p) = app\models\PersonalAccessToken::createToken($user); echo $t->id.'|'.$p;`
  Pastikan proses console & server memakai **DB yang sama**.
- **Hanya boleh ada satu server di port 8899.** Proses `php.exe` lama yang masih hidup akan menjawab
  dengan DB lamanya (gejala: semua request `401` padahal token benar). Bersihkan dengan
  `Get-Process php | Stop-Process -Force` lalu cek `netstat -ano | Select-String ':8899.*LISTENING'`.
- `Yii\db\Query::one()` mengembalikan **`false`** (bukan `null`) bila tidak ada baris — normalisasi dulu
  sebelum indexing (`$row === false ? null : $row`).
- **Validasi enum**: isi nilai `in:` sesuai `information_schema.columns` (`column_type`) agar input salah
  memberi `422`, bukan error DB (`500`).
- Saat mengirim JSON lewat PowerShell 5.1, tulis body ke file lalu `curl.exe ... --data-binary "@file"`
  (kutipan di dalam argumen native ikut termakan).

**Membatasi klien per modul (mis. kasir POS).** Pembatasan UI dilakukan di Astro (env
`AUREUS_MODULES`, lihat README Astro §10), sedangkan pembatasan **API** memakai role Spatie yang
sudah ada: buat role hanya berisi permission modul tersebut lalu assign ke user klien.

```sql
-- contoh: role POS Cashier = semua permission berawalan pos_
INSERT INTO roles (name, guard_name, created_at, updated_at)
VALUES ('POS Cashier', 'web', NOW(), NOW());
INSERT INTO role_has_permissions (permission_id, role_id)
SELECT p.id, r.id FROM permissions p JOIN roles r ON r.name = 'POS Cashier'
WHERE p.name LIKE 'pos\_%';
-- lalu tautkan user: INSERT INTO model_has_roles (...) atau lewat UI Security.
```

Token user tersebut akan mendapat `403` untuk resource modul lain (mis. `sales/orders`), sehingga
instance POS di sisi client tidak bisa membaca data di luar modulnya walau URL API diketik manual.

## 16. Import Produk dari CSV Toko Meka

Data contoh `BACKUP/toko-meka/example/products.csv` (�32.000 baris, 32 kolom) sudah diimpor ke
`products_products` melalui skrip idempoten:

```powershell
cd E:\laragon6\www\ERP\aureuserp-yii2
php scripts/import-toko-meka-products.php            # import penuh
php scripts/import-toko-meka-products.php --limit=200  # uji sebagian
```

Yang dilakukan skrip:

- Menambah kolom `toko_*` pada `products_products` agar seluruh kolom CSV tersimpan:
  `toko_barcode_2/3`, `toko_supplier`, `toko_purchase_date`, `toko_content_qty/unit`,
  `toko_uom_2/3`, `toko_price_2/3`, `toko_wholesale_1..3`, `toko_branch_1..3`,
  `toko_location`, `toko_size`, `toko_color`, `toko_alt_name_2/3`, `toko_stock_min/max`,
  `toko_stock_store`, `toko_stock_warehouse`.
- Pemetaan utama: `KODE_BARANG ? reference`, `KODE_BARCODE ? barcode`, `NAMA ? name`,
  `HPP ? cost`, `HARGA_TOKO_1 (fallback 2/3, partai, cabang) ? price`, `SATUAN_1 ? uom_id`
  (default Units), `KATEGORI / SUB_KATEGORI ? products_categories` di bawah root **Toko Meka**.
- Produk diset `type=goods`, `is_storable=1`, `available_in_pos=1`, `enable_sales/purchase=1`.
- **Kebijakan barcode**: barcode kosong atau duplikat disimpan `NULL` (unique index
  `company_id+barcode` tidak dilanggar); jumlahnya dilaporkan di akhir.
- Idempoten: baris dicocokkan lewat `reference`; aman dijalankan ulang (data di-update, bukan
  diduplikasi).

Hasil import (DB asli): **32.199 produk** (32.193 storable & POS-available), 64 kategori
(root *Toko Meka* + 53 sub-kategori langsung), 209 barcode duplikat jadi `NULL`.

Catatan: produk hasil import **belum** ditautkan ke kategori POS (`pos_category_products`), jadi
katalog kasir tetap ringkas. Untuk memunculkannya di kasir, tautkan kategori produk ke kategori POS
sesuai kebutuhan, mis.:

```sql
-- contoh: jadikan kategori produk "Toko Meka / MINUMAN" sebagai kategori POS baru
INSERT INTO pos_categories (name, color, sort, company_id, created_at, updated_at)
VALUES ('Minuman', '#0ea5e9', 1, 1, NOW(), NOW());
INSERT INTO pos_category_products (category_id, product_id)
SELECT LAST_INSERT_ID(), p.id FROM products_products p
JOIN products_categories c ON c.id = p.category_id
WHERE c.full_name LIKE 'Toko Meka / MINUMAN%';
```

## 17. Relasi & Scope Akses (Yii2)

Di Yii2, query relasi dibangun lewat `$class::find()`. Karena `BaseModel::find()` mengembalikan
`ScopedQuery` (soft-delete + company-scope + allowed-company + ownership), relasi seperti
`manager`, `partner`, `user`, dan `warehouse` **ikut terfilter scope akses** sehingga bisa
mengembalikan `NULL` walaupun foreign key-nya valid (contoh nyata: `employees_departments.manager_id = 1`
tetapi `include=manager` mengembalikan `null`, dan halaman menampilkan `—`).

Perbaikan yang dipakai:

- `components/ScopedQuery.php` — tambah `withoutAccessScopes()`: mematikan scope akses
  (company / allowed-company / ownership), **soft-delete tetap berlaku**.
- `components/BaseModel.php` — override `createRelationQuery()` agar memakai
  `$class::find()->withoutAccessScopes()`.

Kontrol akses tetap dijaga oleh resource utama (list/detail), jadi tidak ada data yang bocor;
perubahan ini hanya membuat relasi menampilkan baris yang memang direferensikan.

Cara mendiagnosa cepat: jika kolom relasi tampil `—` padahal kolom FK terisi, periksa
(a) apakah baris tujuan **soft-deleted** (`deleted_at`), dan (b) apakah scope akses
memfilter baris tersebut.
