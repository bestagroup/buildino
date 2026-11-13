-- MySQL / MariaDB. Requires the expense hierarchy migrations.
-- Run the complete script in one connection. Creates defaults for ALL non-deleted buildings.
SET NAMES utf8mb4;
CREATE TEMPORARY TABLE buildino_category_seed (seed_key VARCHAR(100) PRIMARY KEY, parent_key VARCHAR(100), title VARCHAR(255)) CHARACTER SET utf8mb4;
INSERT INTO buildino_category_seed VALUES
('general', NULL, 'مدیریت، مالی و امور عمومی'),
('general.1', 'general', 'شارژ ثابت ساختمان'),
('general.2', 'general', 'هزینه‌های جاری ساختمان'),
('general.3', 'general', 'هزینه‌های مدیریت ساختمان'),
('general.4', 'general', 'هزینه‌های اداری و دفتری'),
('legal.1', 'general', 'بیمه مسئولیت ساختمان'),
('legal.2', 'general', 'بیمه آتش‌سوزی'),
('legal.3', 'general', 'هزینه‌های حقوقی و مشاوره'),
('legal.4', 'general', 'امور ثبتی و قانونی'),
('legal.5', 'general', 'کارشناسی و بازرسی فنی'),
('misc.1', 'general', 'هزینه‌های پیش‌بینی‌نشده'),
('misc.2', 'general', 'هزینه‌های اضطراری'),
('misc.3', 'general', 'هزینه‌های مناسبتی و رفاهی'),
('misc.4', 'general', 'هزینه‌های متفرقه'),
('energy', NULL, 'انرژی و مصرفی'),
('energy.1', 'energy', 'گاز عمومی ساختمان'),
('energy.2', 'energy', 'برق عمومی ساختمان'),
('energy.3', 'energy', 'آب عمومی ساختمان'),
('energy.4', 'energy', 'سوخت موتورخانه'),
('energy.5', 'energy', 'سرویس کنتورهای عمومی'),
('cleaning', NULL, 'نظافت، بهداشت و محوطه'),
('cleaning.1', 'cleaning', 'نظافت مشاعات'),
('cleaning.2', 'cleaning', 'نظافت پارکینگ'),
('cleaning.3', 'cleaning', 'نظافت راه‌پله و راهروها'),
('cleaning.4', 'cleaning', 'شست‌وشوی مخازن آب'),
('cleaning.5', 'cleaning', 'دفع زباله و پسماند'),
('cleaning.6', 'cleaning', 'سم‌پاشی و مبارزه با حشرات'),
('cleaning.7', 'cleaning', 'خرید مواد شوینده و لوازم نظافت'),
('landscape.1', 'cleaning', 'نگهداری فضای سبز'),
('landscape.2', 'cleaning', 'خرید گل و گیاه'),
('landscape.3', 'cleaning', 'هرس درختان'),
('landscape.4', 'cleaning', 'نگهداری سیستم آبیاری'),
('landscape.5', 'cleaning', 'نظافت و نگهداری محوطه'),
('maintenance', NULL, 'تعمیرات، نگهداری و بهسازی'),
('maintenance.1', 'maintenance', 'تعمیرات تجهیزات ساختمان'),
('maintenance.2', 'maintenance', 'تعمیرات تأسیسات مکانیکی'),
('maintenance.3', 'maintenance', 'تعمیرات تأسیسات برقی'),
('maintenance.4', 'maintenance', 'سرویس و نگهداری آسانسور'),
('maintenance.5', 'maintenance', 'تعمیرات پمپ آب'),
('maintenance.6', 'maintenance', 'تعمیرات موتورخانه'),
('maintenance.7', 'maintenance', 'تعمیرات سیستم سرمایشی'),
('maintenance.8', 'maintenance', 'تعمیرات درب پارکینگ'),
('maintenance.9', 'maintenance', 'تعمیرات لوله‌کشی و فاضلاب'),
('maintenance.10', 'maintenance', 'تعمیرات سیستم روشنایی'),
('maintenance.11', 'maintenance', 'تعمیرات نمای ساختمان'),
('capital.1', 'maintenance', 'بازسازی مشاعات'),
('capital.2', 'maintenance', 'نوسازی تجهیزات'),
('capital.3', 'maintenance', 'رنگ‌آمیزی ساختمان'),
('capital.4', 'maintenance', 'عایق‌کاری و آب‌بندی'),
('capital.5', 'maintenance', 'بهسازی و زیباسازی محوطه'),
('capital.6', 'maintenance', 'خرید تجهیزات جدید'),
('staff', NULL, 'نیروی انسانی و خدمات'),
('staff.1', 'staff', 'حقوق سرایدار'),
('staff.2', 'staff', 'حقوق نگهبان'),
('staff.3', 'staff', 'حقوق نیروی خدماتی'),
('staff.4', 'staff', 'بیمه و مزایای کارکنان'),
('staff.5', 'staff', 'اضافه‌کاری و خدمات موردی'),
('safety', NULL, 'ایمنی و امنیت'),
('safety.1', 'safety', 'نگهداری دوربین مداربسته'),
('safety.2', 'safety', 'سیستم اعلام و اطفای حریق'),
('safety.3', 'safety', 'سرویس کپسول آتش‌نشانی'),
('safety.4', 'safety', 'سیستم کنترل تردد'),
('safety.5', 'safety', 'تجهیزات امنیتی'),
('supplies', NULL, 'تجهیزات و ملزومات'),
('supplies.1', 'supplies', 'خرید ابزارآلات'),
('supplies.2', 'supplies', 'خرید لوازم مصرفی ساختمان'),
('supplies.3', 'supplies', 'خرید تجهیزات روشنایی'),
('supplies.4', 'supplies', 'خرید تجهیزات نظافتی'),
('supplies.5', 'supplies', 'خرید تجهیزات موتورخانه'),
('supplies.6', 'supplies', 'خرید تجهیزات ایمنی'),
('supplies.7', 'supplies', 'خرید تجهیزات مشاعات');
START TRANSACTION;
SELECT id FROM buildings WHERE deleted_at IS NULL ORDER BY id FOR UPDATE;
INSERT INTO financial_categories (building_id,parent_id,title,type,is_active,default_key,title_key,created_at,updated_at)
SELECT b.id,NULL,s.title,'expense',1,s.seed_key,SHA2(CONCAT('0|',TRIM(REPLACE(REPLACE(REPLACE(s.title, 'ي', 'ی'), 'ك', 'ک'), '‌', ' '))),256),NOW(),NOW()
FROM buildings b CROSS JOIN buildino_category_seed s WHERE b.deleted_at IS NULL AND s.parent_key IS NULL
ON DUPLICATE KEY UPDATE id=financial_categories.id;
-- Reparent existing default children without changing their IDs, titles or active state.
UPDATE financial_categories c
JOIN buildino_category_seed s ON s.seed_key=c.default_key
JOIN financial_categories p ON p.building_id=c.building_id AND p.default_key=s.parent_key AND p.parent_id IS NULL
SET c.parent_id=p.id, c.title_key=SHA2(CONCAT(p.id,'|',TRIM(REPLACE(REPLACE(REPLACE(c.title,'ي','ی'),'ك','ک'),'‌',' '))),256)
WHERE c.type='expense' AND s.parent_key IS NOT NULL AND c.parent_id<>p.id;
INSERT INTO financial_categories (building_id,parent_id,title,type,is_active,default_key,title_key,created_at,updated_at)
SELECT p.building_id,p.id,s.title,'expense',1,s.seed_key,SHA2(CONCAT(p.id,'|',TRIM(REPLACE(REPLACE(REPLACE(s.title, 'ي', 'ی'), 'ك', 'ک'), '‌', ' '))),256),NOW(),NOW()
FROM buildino_category_seed s JOIN financial_categories p ON p.default_key=s.parent_key AND p.parent_id IS NULL AND p.type='expense'
JOIN buildings b ON b.id=p.building_id AND b.deleted_at IS NULL
WHERE s.parent_key IS NOT NULL
ON DUPLICATE KEY UPDATE id=financial_categories.id;
UPDATE financial_categories SET title='مدیریت، مالی و امور عمومی', title_key=SHA2(CONCAT('0|','مدیریت، مالی و امور عمومی'),256) WHERE type='expense' AND building_id IS NOT NULL AND parent_id IS NULL AND default_key='general' AND title='عمومی و شارژ';
UPDATE financial_categories SET title='انرژی و مصرفی', title_key=SHA2(CONCAT('0|','انرژی و مصرفی'),256) WHERE type='expense' AND building_id IS NOT NULL AND parent_id IS NULL AND default_key='energy' AND title='انرژی و مصرفی';
UPDATE financial_categories SET title='نظافت، بهداشت و محوطه', title_key=SHA2(CONCAT('0|','نظافت، بهداشت و محوطه'),256) WHERE type='expense' AND building_id IS NOT NULL AND parent_id IS NULL AND default_key='cleaning' AND title='نظافت و بهداشت';
UPDATE financial_categories SET title='تعمیرات، نگهداری و بهسازی', title_key=SHA2(CONCAT('0|','تعمیرات، نگهداری و بهسازی'),256) WHERE type='expense' AND building_id IS NOT NULL AND parent_id IS NULL AND default_key='maintenance' AND title='تعمیرات و نگهداری';
UPDATE financial_categories SET title='نیروی انسانی و خدمات', title_key=SHA2(CONCAT('0|','نیروی انسانی و خدمات'),256) WHERE type='expense' AND building_id IS NOT NULL AND parent_id IS NULL AND default_key='staff' AND title='نیروی انسانی و خدمات';
UPDATE financial_categories SET title='ایمنی و امنیت', title_key=SHA2(CONCAT('0|','ایمنی و امنیت'),256) WHERE type='expense' AND building_id IS NOT NULL AND parent_id IS NULL AND default_key='safety' AND title='ایمنی و امنیت';
UPDATE financial_categories SET title='تجهیزات و ملزومات', title_key=SHA2(CONCAT('0|','تجهیزات و ملزومات'),256) WHERE type='expense' AND building_id IS NOT NULL AND parent_id IS NULL AND default_key='supplies' AND title='خرید تجهیزات و ملزومات';
-- Retain obsolete roots for historical references; disable only roots with no active children.
UPDATE financial_categories p LEFT JOIN financial_categories c ON c.parent_id=p.id AND c.is_active=1
SET p.is_active=0 WHERE p.type='expense' AND p.building_id IS NOT NULL AND p.default_key IN ('legal','misc','landscape','capital') AND c.id IS NULL;
COMMIT;
DROP TEMPORARY TABLE buildino_category_seed;
SELECT building_id,COUNT(*) AS active_roots FROM financial_categories WHERE type='expense' AND parent_id IS NULL AND is_active=1 GROUP BY building_id;
