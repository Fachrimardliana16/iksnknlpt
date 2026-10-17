# Skema Basis Data - Iksan Knalpot

Pendamping `PRD.md` Bagian 9. SQLite, `journal_mode=WAL`, `synchronous=NORMAL`.

## Prinsip

1. Kunci primer internal `id INTEGER PRIMARY KEY AUTOINCREMENT` untuk kecepatan relasi.
2. Setiap tabel yang punya rute publik menyimpan `ulid TEXT UNIQUE NOT NULL`. Rute publik memakai ULID, bukan `id`.
3. Kolom PII memakai cast `encrypted`. Email memakai `encrypted` + `email_bidx` (HMAC-SHA256) karena kolom terenkripsi tidak bisa di-`LIKE`.
4. Uang memakai `DECIMAL(12,2)`. SQLite menyimpannya sebagai NUMERIC; pembacaan di Laravel memakai cast `decimal:2`.
5. Waktu memakai `DATETIME`. Utc di basis data, ditampilkan dalam WIB.

Migration dipecah per domain, bukan satu berkas raksasa.

---

## 1. users

```sql
CREATE TABLE users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    ulid TEXT UNIQUE NOT NULL,
    name TEXT NOT NULL,                      -- encrypted
    email TEXT NOT NULL,                     -- encrypted
    email_bidx TEXT UNIQUE NOT NULL,         -- HMAC-SHA256
    email_verified_at DATETIME NULL,
    password TEXT NOT NULL,                  -- Argon2id
    phone_number TEXT NULL,                  -- encrypted
    role TEXT NOT NULL DEFAULT 'customer'
        CHECK(role IN ('customer','admin','super_admin')),
    two_factor_secret TEXT NULL,             -- encrypted
    two_factor_recovery_codes TEXT NULL,     -- encrypted
    two_factor_confirmed_at DATETIME NULL,
    is_blocked INTEGER NOT NULL DEFAULT 0 CHECK(is_blocked IN (0,1)),
    last_login_at DATETIME NULL,
    last_login_ip_hash TEXT NULL,
    remember_token TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_users_role       ON users(role);
CREATE INDEX idx_users_blocked    ON users(is_blocked);
```

## 2. addresss

```sql
CREATE TABLE addresses (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    ulid TEXT UNIQUE NOT NULL,
    user_id INTEGER NOT NULL,
    label TEXT NOT NULL DEFAULT 'Rumah',
    recipient_name TEXT NOT NULL,            -- encrypted
    phone_number TEXT NOT NULL,              -- encrypted
    address_line_1 TEXT NOT NULL,            -- encrypted
    address_line_2 TEXT NULL,                -- encrypted
    city TEXT NOT NULL,
    state_province TEXT NOT NULL,
    postal_code TEXT NOT NULL,
    is_primary INTEGER NOT NULL DEFAULT 0 CHECK(is_primary IN (0,1)),
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE INDEX idx_addresses_user ON addresses(user_id);
```

## 3. categories

```sql
CREATE TABLE categories (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    ulid TEXT UNIQUE NOT NULL,
    name_id TEXT NOT NULL,
    name_en TEXT NOT NULL,
    description_id TEXT NULL,
    description_en TEXT NULL,
    slug TEXT UNIQUE NOT NULL,
    image_path TEXT NULL,
    display_order INTEGER NOT NULL DEFAULT 0,
    is_active INTEGER NOT NULL DEFAULT 1 CHECK(is_active IN (0,1)),
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_categories_slug   ON categories(slug);
CREATE INDEX idx_categories_active ON categories(is_active, display_order);
```

## 4. products

```sql
CREATE TABLE products (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    ulid TEXT UNIQUE NOT NULL,
    category_id INTEGER NOT NULL,
    sku TEXT UNIQUE NOT NULL,
    title_id TEXT NOT NULL,
    title_en TEXT NOT NULL,
    slug TEXT UNIQUE NOT NULL,
    excerpt_id TEXT NULL,
    excerpt_en TEXT NULL,
    description_id TEXT NOT NULL,
    description_en TEXT NOT NULL,
    base_price DECIMAL(12,2) NOT NULL,
    weight_gram INTEGER NOT NULL DEFAULT 1000,
    is_featured INTEGER NOT NULL DEFAULT 0 CHECK(is_featured IN (0,1)),
    is_published INTEGER NOT NULL DEFAULT 1 CHECK(is_published IN (0,1)),
    view_count INTEGER NOT NULL DEFAULT 0,
    sold_count INTEGER NOT NULL DEFAULT 0,
    published_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE RESTRICT
);

CREATE INDEX idx_products_category ON products(category_id);
CREATE INDEX idx_products_slug     ON products(slug);
CREATE INDEX idx_products_featured ON products(is_featured, is_published, published_at);
CREATE INDEX idx_products_listing  ON products(is_published, created_at);
```

`is_featured` dan `is_published` disimpan sebagai integer karena SQLite tidak punya tipe boolean asli. Cast Laravel `boolean` menanganinya di sisi aplikasi.

## 5. product_variants

```sql
CREATE TABLE product_variants (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    ulid TEXT UNIQUE NOT NULL,
    product_id INTEGER NOT NULL,
    sku_variant TEXT UNIQUE NOT NULL,
    variant_name_id TEXT NOT NULL,
    variant_name_en TEXT NOT NULL,
    price_adjustment DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    stock_quantity INTEGER NOT NULL DEFAULT 0 CHECK(stock_quantity >= 0),
    reserved_quantity INTEGER NOT NULL DEFAULT 0 CHECK(reserved_quantity >= 0),
    weight_gram INTEGER NULL,
    display_order INTEGER NOT NULL DEFAULT 0,
    is_active INTEGER NOT NULL DEFAULT 1 CHECK(is_active IN (0,1)),
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
);

CREATE INDEX idx_variants_product ON product_variants(product_id, is_active);
CREATE INDEX idx_variants_stock   ON product_variants(stock_quantity);
```

`reserved_quantity` menahan stok pada pesanan `pending` yang belum dibayar. Stok yang dapat dijual = `stock_quantity - reserved_quantity`.

## 6. product_media

```sql
CREATE TABLE product_media (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    ulid TEXT UNIQUE NOT NULL,
    product_id INTEGER NOT NULL,
    type TEXT NOT NULL CHECK(type IN ('image','video','audio','model_3d')),
    original_filename TEXT NULL,
    path_original TEXT NULL,
    path_thumb TEXT NULL,      -- 320x240
    path_card TEXT NULL,       -- 640x480
    path_full TEXT NULL,       -- 1600x1200
    path_og TEXT NULL,         -- 1200x630
    width INTEGER NULL,
    height INTEGER NULL,
    byte_size INTEGER NULL,
    mime_detected TEXT NULL,
    content_hash TEXT NULL,    -- SHA-256
    alt_text_id TEXT NULL,
    alt_text_en TEXT NULL,
    is_primary INTEGER NOT NULL DEFAULT 0 CHECK(is_primary IN (0,1)),
    display_order INTEGER NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
);

CREATE INDEX idx_media_product  ON product_media(product_id, display_order);
CREATE INDEX idx_media_primary  ON product_media(product_id, is_primary);
CREATE INDEX idx_media_hash     ON product_media(content_hash);
```

## 7. carts / cart_items

```sql
CREATE TABLE carts (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    ulid TEXT UNIQUE NOT NULL,
    user_id INTEGER NULL,
    session_id TEXT NULL,
    coupon_id INTEGER NULL,
    expires_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE cart_items (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    ulid TEXT UNIQUE NOT NULL,
    cart_id INTEGER NOT NULL,
    product_id INTEGER NOT NULL,
    product_variant_id INTEGER NULL,
    quantity INTEGER NOT NULL CHECK(quantity > 0),
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (cart_id) REFERENCES carts(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (product_variant_id) REFERENCES product_variants(id) ON DELETE CASCADE
);

CREATE UNIQUE INDEX idx_cart_item_unique ON cart_items(cart_id, product_variant_id);
CREATE INDEX idx_carts_user ON carts(user_id);
CREATE INDEX idx_carts_sess ON carts(session_id);
```

Indeks unik mencegah baris ganda untuk varian yang sama dalam satu keranjang.

## 8. coupons

```sql
CREATE TABLE coupons (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    ulid TEXT UNIQUE NOT NULL,
    code TEXT UNIQUE NOT NULL,
    type TEXT NOT NULL CHECK(type IN ('percent','fixed','free_shipping')),
    value DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    min_spend DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    max_discount DECIMAL(12,2) NULL,
    quota INTEGER NULL,
    used_count INTEGER NOT NULL DEFAULT 0,
    starts_at DATETIME NULL,
    ends_at DATETIME NULL,
    is_active INTEGER NOT NULL DEFAULT 1 CHECK(is_active IN (0,1)),
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_coupons_code   ON coupons(code, is_active);
CREATE INDEX idx_coupons_window ON coupons(starts_at, ends_at);
```

## 9. orders

```sql
CREATE TABLE orders (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    ulid TEXT UNIQUE NOT NULL,
    order_number TEXT UNIQUE NOT NULL,        -- IK-20261017-0001
    user_id INTEGER NULL,                     -- NULL = checkout tamu
    guest_email TEXT NULL,                    -- encrypted
    guest_email_bidx TEXT NULL,
    guest_phone TEXT NULL,                    -- encrypted
    status TEXT NOT NULL DEFAULT 'pending'
        CHECK(status IN ('pending','paid','processing','shipped','completed','cancelled')),
    payment_status TEXT NOT NULL DEFAULT 'unpaid'
        CHECK(payment_status IN ('unpaid','pending','paid','failed','refunded')),
    payment_method TEXT NULL CHECK(payment_method IN ('paypal','manual')),
    subtotal DECIMAL(12,2) NOT NULL,
    discount_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    tax_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    shipping_cost DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    grand_total DECIMAL(12,2) NOT NULL,
    currency TEXT NOT NULL DEFAULT 'IDR',
    coupon_id INTEGER NULL,
    shipping_address_snapshot TEXT NOT NULL,  -- encrypted JSON
    shipping_courier TEXT NULL,
    shipping_service TEXT NULL,
    tracking_number TEXT NULL,
    customer_note TEXT NULL,
    admin_note TEXT NULL,
    paid_at DATETIME NULL,
    shipped_at DATETIME NULL,
    completed_at DATETIME NULL,
    cancelled_at DATETIME NULL,
    expires_at DATETIME NULL,                 -- auto-batal 24 jam
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (coupon_id) REFERENCES coupons(id) ON DELETE SET NULL
);

CREATE INDEX idx_orders_number   ON orders(order_number);
CREATE INDEX idx_orders_status   ON orders(status, created_at);
CREATE INDEX idx_orders_user     ON orders(user_id, created_at);
CREATE INDEX idx_orders_payment  ON orders(payment_status);
CREATE INDEX idx_orders_expires  ON orders(status, expires_at);
```

## 10. order_items

```sql
CREATE TABLE order_items (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    ulid TEXT UNIQUE NOT NULL,
    order_id INTEGER NOT NULL,
    product_id INTEGER NOT NULL,
    product_variant_id INTEGER NULL,
    product_name TEXT NOT NULL,               -- snapshot saat transaksi
    variant_name TEXT NULL,
    sku TEXT NOT NULL,
    unit_price DECIMAL(12,2) NOT NULL,
    quantity INTEGER NOT NULL CHECK(quantity > 0),
    subtotal DECIMAL(12,2) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE RESTRICT,
    FOREIGN KEY (product_variant_id) REFERENCES product_variants(id) ON DELETE SET NULL
);

CREATE INDEX idx_order_items_order ON order_items(order_id);
```

Snapshot nama dan harga wajib. Harga produk bisa berubah setelah pesanan dibuat, dan invoice lama tidak boleh ikut berubah.

## 11. order_status_history

```sql
CREATE TABLE order_status_history (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    ulid TEXT UNIQUE NOT NULL,
    order_id INTEGER NOT NULL,
    from_status TEXT NULL,
    to_status TEXT NOT NULL,
    note TEXT NULL,
    changed_by INTEGER NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (changed_by) REFERENCES users(id) ON DELETE SET NULL
);

CREATE INDEX idx_osh_order ON order_status_history(order_id, created_at);
```

## 12. payments

```sql
CREATE TABLE payments (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    ulid TEXT UNIQUE NOT NULL,
    order_id INTEGER NOT NULL,
    provider TEXT NOT NULL CHECK(provider IN ('paypal','manual')),
    provider_transaction_id TEXT NULL,
    provider_status TEXT NULL,
    amount DECIMAL(12,2) NOT NULL,
    currency TEXT NOT NULL DEFAULT 'IDR',
    proof_path TEXT NULL,                     -- unggahan bukti transfer
    payload_json TEXT NULL,                   -- muatan webhook
    verified_by INTEGER NULL,
    verified_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (verified_by) REFERENCES users(id) ON DELETE SET NULL
);

CREATE UNIQUE INDEX idx_payments_txn   ON payments(provider, provider_transaction_id);
CREATE INDEX        idx_payments_order ON payments(order_id);
```

Indeks unik pada pasangan penyedia dan ID transaksi mencegah webhook ganda tercatat dua kali.

## 13. settings

```sql
CREATE TABLE settings (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    key TEXT UNIQUE NOT NULL,
    value TEXT NULL,
    group_name TEXT NOT NULL DEFAULT 'general',
    type TEXT NOT NULL DEFAULT 'text'
        CHECK(type IN ('text','textarea','number','boolean','image','email','url','select','json')),
    label TEXT NULL,
    options_json TEXT NULL,
    is_encrypted INTEGER NOT NULL DEFAULT 0 CHECK(is_encrypted IN (0,1)),
    is_public INTEGER NOT NULL DEFAULT 0 CHECK(is_public IN (0,1)),
    updated_by INTEGER NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
);

CREATE INDEX idx_settings_group ON settings(group_name);
```

`is_encrypted = 1` untuk kredensial pembayaran. `is_public = 1` untuk nilai yang boleh dikirim ke sisi depan (`site_name`, `whatsapp_number`), sehingga satu kueri dapat memuat seluruh pengaturan publik sekaligus.

## 14. hero_slides

```sql
CREATE TABLE hero_slides (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    ulid TEXT UNIQUE NOT NULL,
    title_id TEXT NULL,
    title_en TEXT NULL,
    subtitle_id TEXT NULL,
    subtitle_en TEXT NULL,
    media_type TEXT NOT NULL DEFAULT 'image' CHECK(media_type IN ('image','video')),
    media_path TEXT NOT NULL,
    media_path_mobile TEXT NULL,
    poster_path TEXT NULL,
    focal_point_x REAL NOT NULL DEFAULT 0.5,
    focal_point_y REAL NOT NULL DEFAULT 0.5,
    primary_button_label_id TEXT NULL,
    primary_button_label_en TEXT NULL,
    primary_button_url TEXT NULL,
    secondary_button_label_id TEXT NULL,
    secondary_button_label_en TEXT NULL,
    secondary_button_url TEXT NULL,
    starts_at DATETIME NULL,
    ends_at DATETIME NULL,
    display_order INTEGER NOT NULL DEFAULT 0,
    is_active INTEGER NOT NULL DEFAULT 1 CHECK(is_active IN (0,1)),
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_hero_active ON hero_slides(is_active, display_order);
```

## 15. contact_messages

```sql
CREATE TABLE contact_messages (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    ulid TEXT UNIQUE NOT NULL,
    name TEXT NOT NULL,                       -- encrypted
    email TEXT NOT NULL,                      -- encrypted
    email_bidx TEXT NULL,
    phone TEXT NULL,                          -- encrypted
    subject TEXT NULL,
    message TEXT NOT NULL,                    -- encrypted
    ip_hash TEXT NULL,
    user_agent TEXT NULL,
    is_read INTEGER NOT NULL DEFAULT 0 CHECK(is_read IN (0,1)),
    replied_at DATETIME NULL,
    replied_by INTEGER NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (replied_by) REFERENCES users(id) ON DELETE SET NULL
);

CREATE INDEX idx_contact_unread ON contact_messages(is_read, created_at);
```

## 16. security_logs

```sql
CREATE TABLE security_logs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    ulid TEXT UNIQUE NOT NULL,
    ip_hash TEXT NOT NULL,
    event_type TEXT NOT NULL CHECK(event_type IN (
        'login_failed','login_success','brute_force_blocked','ip_blocked',
        'unauthorized_access','rate_limit_exceeded','two_factor_failed',
        'upload_rejected','admin_action'
    )),
    user_id INTEGER NULL,
    email_attempted TEXT NULL,                -- encrypted
    route TEXT NULL,
    user_agent TEXT NULL,
    payload_summary TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

CREATE INDEX idx_sec_event ON security_logs(event_type, created_at);
CREATE INDEX idx_sec_ip    ON security_logs(ip_hash, created_at);
```

## 17. login_attempts / blocked_ips

```sql
CREATE TABLE login_attempts (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    ip_hash TEXT NOT NULL,
    email_bidx TEXT NULL,
    attempts INTEGER NOT NULL DEFAULT 0,
    last_attempt_at DATETIME NOT NULL,
    locked_until DATETIME NULL
);

CREATE UNIQUE INDEX idx_login_attempts ON login_attempts(ip_hash, email_bidx);

CREATE TABLE blocked_ips (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    ip_hash TEXT NOT NULL UNIQUE,
    reason TEXT NOT NULL,
    blocked_until DATETIME NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_blocked_until ON blocked_ips(blocked_until);
```

Tabel ini menyimpan hash IP, bukan IP mentah. Pembukaan blokir manual dari `/admin/keamanan` menghapus barisnya.

## 18. activity_logs

```sql
CREATE TABLE activity_logs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    ulid TEXT UNIQUE NOT NULL,
    user_id INTEGER NULL,
    action TEXT NOT NULL,
    subject_type TEXT NULL,
    subject_id INTEGER NULL,
    changes_json TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

CREATE INDEX idx_activity_subject ON activity_logs(subject_type, subject_id, created_at);
```

Nilai terenkripsi dicatat sebagai `[changed]`, tidak pernah berisi nilai lama atau baru.

## 19. media_upload_logs

```sql
CREATE TABLE media_upload_logs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    ulid TEXT UNIQUE NOT NULL,
    user_id INTEGER NULL,
    original_filename TEXT NOT NULL,
    byte_size INTEGER NOT NULL,
    mime_declared TEXT NULL,
    mime_detected TEXT NULL,
    content_hash TEXT NOT NULL,
    status TEXT NOT NULL CHECK(status IN ('accepted','rejected','failed','deduplicated')),
    rejection_reason TEXT NULL,
    product_media_id INTEGER NULL,
    processing_ms INTEGER NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (product_media_id) REFERENCES product_media(id) ON DELETE SET NULL
);

CREATE INDEX idx_upload_status ON media_upload_logs(status, created_at);
CREATE INDEX idx_upload_hash   ON media_upload_logs(content_hash);
```

## 20. Riwayat migration

| # | Berkas | Tabel |
| :--- | :--- | :--- |
| 0001 | `create_users_table` | users |
| 0002 | `create_cache_and_jobs_tables` | cache, jobs (bawaan Laravel) |
| 0003 | `create_addresses_table` | addresses |
| 0004 | `create_categories_table` | categories |
| 0005 | `create_products_table` | products |
| 0006 | `create_product_variants_table` | product_variants |
| 0007 | `create_product_media_table` | product_media |
| 0008 | `create_carts_tables` | carts, cart_items |
| 0009 | `create_coupons_table` | coupons |
| 0010 | `create_orders_table` | orders |
| 0011 | `create_order_items_table` | order_items |
| 0012 | `create_order_status_history_table` | order_status_history |
| 0013 | `create_payments_table` | payments |
| 0014 | `create_settings_table` | settings |
| 0015 | `create_hero_slides_table` | hero_slides |
| 0016 | `create_contact_messages_table` | contact_messages |
| 0017 | `create_security_logs_table` | security_logs |
| 0018 | `create_login_attempts_tables` | login_attempts, blocked_ips |
| 0019 | `create_activity_logs_table` | activity_logs |
| 0020 | `create_media_upload_logs_table` | media_upload_logs |

Urutan wajib dijaga karena kunci asing. `orders` harus dibuat setelah `coupons` dan `users`.

## 21. Catatan operasional SQLite

Jalankan saat aplikasi pertama kali boot, lewat penyedia layanan:

```sql
PRAGMA journal_mode = WAL;
PRAGMA synchronous = NORMAL;
PRAGMA foreign_keys = ON;
PRAGMA busy_timeout = 5000;
PRAGMA cache_size = -20000;
PRAGMA temp_store = MEMORY;
```

- `foreign_keys = ON` wajib. SQLite mematikannya secara bawaan, dan tanpa ini seluruh kunci asing di dokumen ini menjadi dekorasi.
- `busy_timeout = 5000` mencegah galat "database is locked" saat penulisan bersamaan.
- Batas penulisan bersamaan SQLite rendah. Operasi tulis berat (pengurangan stok, pembuatan pesanan) harus dibungkus transaksi yang singkat.
- `migrate:fresh` pada produksi diblokir dengan pemeriksaan `app()->environment('production')` di dalam `DatabaseSeeder`.
