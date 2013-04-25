
INSERT INTO grants (grant_name) VALUES ('ACCESS'), 
('USER_INSERT'), ('USER_SELECT'), ('USER_UPDATE'), ('USER_DELETE'), 
('TOKEN_INSERT'), ('TOKEN_SELECT'), ('TOKEN_UPDATE'), ('TOKEN_DELETE'), 
('GROUP_INSERT'), ('GROUP_SELECT'), ('GROUP_UPDATE'), ('GROUP_DELETE'), 
	('GROUP_USER_INSERT'), ('GROUP_USER_SELECT'), ('GROUP_USER_DELETE'), 
('GRANT_INSERT'), ('GRANT_SELECT'), ('GRANT_UPDATE'), ('GRANT_DELETE'), 
	('GRANT_GROUP_INSERT'), ('GRANT_GROUP_SELECT'), ('GRANT_GROUP_DELETE'), 
	('GRANT_TOKEN_INSERT'), ('GRANT_TOKEN_SELECT'), ('GRANT_TOKEN_DELETE'), 
	('GRANT_USER_INSERT'), ('GRANT_USER_SELECT'), ('GRANT_USER_DELETE'), 
('SELF_SELECT'), ('SELF_UPDATE'), ('SELF_DELETE'), ('SELF_GRANT_SELECT'), ('SELF_GROUP_SELECT'), ('SELF_GROUP_DELETE'), 
	('SELF_TOKEN_INSERT'), ('SELF_TOKEN_SELECT'), ('SELF_TOKEN_UPDATE'), ('SELF_TOKEN_DELETE'), ('SELF_TOKEN_GRANT_DELETE'), ('SELF_TOKEN_GRANT_INSERT');
INSERT INTO groups (group_name) VALUES ('ADMIN_USER'), ('ADMIN_TOKEN'), ('ADMIN_GROUP'), ('ADMIN_GRANT'), ('USERS');
INSERT INTO users (user_name, user_ldap) VALUES ('admin', 0);
INSERT INTO tokens (token_value, token_lease, token_user) VALUES (MD5(UNIX_TIMESTAMP()), 0, (SELECT user_id FROM users where user_name = 'admin'));
INSERT INTO user_grant (user_id, grant_id) SELECT user_id, grant_id FROM users, grants WHERE user_name='admin';
INSERT INTO token_grant (token_id, grant_id) SELECT token_id, grant_id FROM tokens, grants;
INSERT INTO group_grant (group_id, grant_id) SELECT group_id, grant_id FROM groups, grants WHERE group_name='ADMIN_USER' AND grant_name LIKE 'USER%';
INSERT INTO group_grant (group_id, grant_id) SELECT group_id, grant_id FROM groups, grants WHERE group_name='ADMIN_TOKEN' AND grant_name LIKE 'TOKEN%';
INSERT INTO group_grant (group_id, grant_id) SELECT group_id, grant_id FROM groups, grants WHERE group_name='ADMIN_GROUP' AND grant_name LIKE 'GROUP%';
INSERT INTO group_grant (group_id, grant_id) SELECT group_id, grant_id FROM groups, grants WHERE group_name='ADMIN_GRANT' AND grant_name LIKE 'GRANT%';
INSERT INTO group_grant (group_id, grant_id) SELECT group_id, grant_id FROM groups, grants WHERE group_name='USERS' AND (grant_name LIKE 'SELF%' OR grant_name='ACCESS');
