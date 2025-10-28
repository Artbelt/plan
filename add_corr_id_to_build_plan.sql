-- Добавление поля corrugation_plan_id в таблицу build_plan

ALTER TABLE build_plan 
ADD COLUMN corrugation_plan_id INT NULL AFTER filter_label;

-- Проверка
DESCRIBE build_plan;


