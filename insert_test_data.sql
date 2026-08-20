-- 清空现有数据
DELETE FROM parcel_status_history;
DELETE FROM parcels;

-- 插入分配给骑手的包裹 (assigned_rider_id = 2 是你的骑手账户)
INSERT INTO parcels (tracking_number, address, status, assigned_rider_id, created_at, updated_at) VALUES
('TRK000001', 'Sunway, Petaling Jaya, Selangor', 'pending', 2, NOW(), NOW()),
('TRK000002', 'Mid Valley, Kuala Lumpur', 'pending', 2, NOW(), NOW()),
('TRK000003', 'Pavilion KL, Bukit Bintang', 'out_for_delivery', 2, NOW(), NOW());

-- 插入可用包裹 (assigned_rider_id = NULL)
INSERT INTO parcels (tracking_number, address, status, assigned_rider_id, created_at, updated_at) VALUES
('TRK000004', 'Bangsar Shopping Centre, Kuala Lumpur', 'pending', NULL, NOW(), NOW()),
('TRK000005', '1 Utama Shopping Centre, Petaling Jaya', 'pending', NULL, NOW(), NOW()),
('TRK000006', 'Jalan SS 15, Subang Jaya', 'pending', NULL, NOW(), NOW());

-- 插入状态历史
INSERT INTO parcel_status_history (parcel_id, status, remarks, created_at) VALUES
(1, 'pending', 'Parcel assigned to rider', NOW()),
(2, 'pending', 'Parcel assigned to rider', NOW()),
(3, 'out_for_delivery', 'Out for delivery', NOW()),
(4, 'pending', 'Available for claiming', NOW()),
(5, 'pending', 'Available for claiming', NOW()),
(6, 'pending', 'Available for claiming', NOW());
