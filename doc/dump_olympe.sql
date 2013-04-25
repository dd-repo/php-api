
INSERT INTO grants (grant_name) VALUES 
('QUOTA_INSERT'), ('QUOTA_SELECT'), ('QUOTA_UPDATE'), ('QUOTA_DELETE'), 
	('QUOTA_USER_INSERT'), ('QUOTA_USER_SELECT'), ('QUOTA_USER_UPDATE'), ('QUOTA_USER_DELETE'), 
('SELF_QUOTA_SELECT');
INSERT INTO groups (group_name) VALUES ('ADMIN_QUOTA');
INSERT INTO group_grant (group_id, grant_id) SELECT group_id, grant_id FROM groups, grants WHERE group_name='ADMIN_QUOTA' AND grant_name LIKE 'QUOTA%';
INSERT IGNORE INTO group_grant (group_id, grant_id) SELECT group_id, grant_id FROM groups, grants WHERE group_name='USERS' AND grant_name LIKE 'SELF%';
