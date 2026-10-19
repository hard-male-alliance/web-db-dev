-- Galaxy Workshop course demonstration; MySQL 8.4+, UTF-8, InnoDB.
-- Safe schema/bootstrap source; the actual pristine export is data/install.sql.
-- Import into an already-created database: mysql DATABASE < data/schema.sql
-- No database/user creation, destructive reset, or existing-record replacement.
-- Demo only: admin / admin123456; member / member123456. Change before deployment.
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS teams (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 name VARCHAR(100) NOT NULL,
 tagline VARCHAR(255) NOT NULL DEFAULT '',
 description TEXT NOT NULL,
 email VARCHAR(254) NOT NULL DEFAULT '',
 location VARCHAR(120) NOT NULL DEFAULT '',
 updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS members (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 name VARCHAR(100) NOT NULL,
 title VARCHAR(120) NOT NULL DEFAULT '',
 bio TEXT NOT NULL,
 email VARCHAR(254) NOT NULL DEFAULT '',
 avatar_color VARCHAR(16) NOT NULL DEFAULT '#6554c0',
 sort_order INT NOT NULL DEFAULT 0,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS users (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 username VARCHAR(64) NOT NULL UNIQUE,
 password_hash VARCHAR(255) NOT NULL,
 role ENUM('admin','member') NOT NULL DEFAULT 'member',
 member_id INT UNSIGNED NULL UNIQUE,
 enabled BOOLEAN NOT NULL DEFAULT TRUE,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 CONSTRAINT fk_users_member FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS skills (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 name VARCHAR(100) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS member_skills (
 member_id INT UNSIGNED NOT NULL,
 skill_id INT UNSIGNED NOT NULL,
 level TINYINT UNSIGNED NOT NULL DEFAULT 1,
 PRIMARY KEY (member_id, skill_id),
 CONSTRAINT chk_member_skill_level CHECK (level BETWEEN 1 AND 5),
 CONSTRAINT fk_member_skills_member FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE,
 CONSTRAINT fk_member_skills_skill FOREIGN KEY (skill_id) REFERENCES skills(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS projects (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 team_id INT UNSIGNED NOT NULL DEFAULT 1,
 title VARCHAR(200) NOT NULL,
 summary VARCHAR(500) NOT NULL DEFAULT '',
 description TEXT NOT NULL,
 status ENUM('planning','active','completed') NOT NULL DEFAULT 'planning',
 published BOOLEAN NOT NULL DEFAULT FALSE,
 progress TINYINT UNSIGNED NOT NULL DEFAULT 0,
 started_on DATE NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 INDEX idx_projects_public (published,status,id),
 CONSTRAINT chk_projects_progress CHECK (progress BETWEEN 0 AND 100),
 CONSTRAINT fk_projects_team FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS project_members (
 project_id INT UNSIGNED NOT NULL,
 member_id INT UNSIGNED NOT NULL,
 role VARCHAR(120) NOT NULL DEFAULT '贡献者',
 PRIMARY KEY (project_id,member_id),
 CONSTRAINT fk_project_members_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
 CONSTRAINT fk_project_members_member FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS updates (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 project_id INT UNSIGNED NULL,
 author_id INT UNSIGNED NOT NULL,
 title VARCHAR(200) NOT NULL,
 body TEXT NOT NULL,
 published BOOLEAN NOT NULL DEFAULT FALSE,
 published_at DATETIME NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 INDEX idx_updates_public (published,published_at,id),
 CONSTRAINT fk_updates_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE SET NULL,
 CONSTRAINT fk_updates_author FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS messages (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 name VARCHAR(100) NOT NULL,
 email VARCHAR(254) NOT NULL DEFAULT '',
 body TEXT NOT NULL,
 status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 INDEX idx_messages_status (status,created_at,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS message_replies (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 message_id INT UNSIGNED NOT NULL,
 body TEXT NOT NULL,
 author_id INT UNSIGNED NOT NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 CONSTRAINT fk_message_replies_message FOREIGN KEY (message_id) REFERENCES messages(id) ON DELETE CASCADE,
 CONSTRAINT fk_message_replies_author FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS milestones (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 project_id INT UNSIGNED NOT NULL,
 title VARCHAR(200) NOT NULL,
 due_on DATE NULL,
 completed BOOLEAN NOT NULL DEFAULT FALSE,
 CONSTRAINT fk_milestones_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS submissions (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 title VARCHAR(200) NOT NULL,
 kind VARCHAR(64) NOT NULL UNIQUE,
 file_name VARCHAR(255) NOT NULL UNIQUE,
 description VARCHAR(500) NOT NULL DEFAULT '',
 sort_order INT NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS audit_logs (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 user_id INT UNSIGNED NULL,
 action VARCHAR(100) NOT NULL,
 entity_type VARCHAR(64) NOT NULL,
 entity_id INT UNSIGNED NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 INDEX idx_audit_entity (entity_type,entity_id),
 INDEX idx_audit_created (created_at),
 CONSTRAINT fk_audit_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS login_attempts (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 ip_hash CHAR(64) NOT NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 INDEX idx_login_attempts_ip_time (ip_hash,created_at),
 INDEX idx_login_attempts_time (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- DDL commits implicitly in MySQL; only demo DML belongs to this transaction.
-- Seed only a completely empty domain. Re-import never restores deleted demo rows,
-- resets passwords, or reattaches fixed demo IDs to real records.
START TRANSACTION;
SET @seed_demo = (
 SELECT (SELECT COUNT(*) FROM teams) + (SELECT COUNT(*) FROM members)
 + (SELECT COUNT(*) FROM users) + (SELECT COUNT(*) FROM skills)
 + (SELECT COUNT(*) FROM member_skills) + (SELECT COUNT(*) FROM projects)
 + (SELECT COUNT(*) FROM project_members) + (SELECT COUNT(*) FROM updates)
 + (SELECT COUNT(*) FROM messages) + (SELECT COUNT(*) FROM message_replies)
 + (SELECT COUNT(*) FROM milestones) + (SELECT COUNT(*) FROM submissions)
 + (SELECT COUNT(*) FROM audit_logs) + (SELECT COUNT(*) FROM login_attempts) = 0
);
INSERT INTO teams (id,name,tagline,description,email,location)
SELECT 1,'星河工坊','把想法连接成星河','我们是一支以数据库与 Web 开发为核心的课程实践团队，以可运行的软件记录协作、学习与创造。','hello@example.test','中国 · 校园' WHERE @seed_demo;
INSERT INTO members (id,name,title,bio,email,avatar_color,sort_order)
SELECT 1,'林星','项目负责人 / 后端开发','关注数据库设计、可靠的接口与清晰的团队协作。此处为可替换的演示成员。','lin@example.test','#6658d3',1 WHERE @seed_demo;
INSERT INTO members (id,name,title,bio,email,avatar_color,sort_order)
SELECT 2,'陈河','前端开发 / 交互设计','让信息易于理解，让每一次交互都有回应。此处为可替换的演示成员。','chen@example.test','#20867d',2 WHERE @seed_demo;
INSERT INTO members (id,name,title,bio,email,avatar_color,sort_order)
SELECT 3,'周远','测试 / 文档','通过真实用户旅程检验系统，并用文档保留团队知识。此处为可替换的演示成员。','zhou@example.test','#c77c36',3 WHERE @seed_demo;
INSERT INTO users (id,username,password_hash,role,member_id)
SELECT 1,'admin','$2y$12$um33yUR7JwK.xLOe2GFGw.23L9LcofsESnFI5fXBHeyrlxloH.3Gi','admin',1 WHERE @seed_demo;
INSERT INTO users (id,username,password_hash,role,member_id)
SELECT 2,'member','$2y$12$QvE.BFC8YNtrg7XFxritP.PSFP6PmwlLmaXT01fQ1LEI8OEl4LITO','member',2 WHERE @seed_demo;
INSERT INTO skills (id,name) SELECT 1,'PHP / Yii2' WHERE @seed_demo;
INSERT INTO skills (id,name) SELECT 2,'MySQL' WHERE @seed_demo;
INSERT INTO skills (id,name) SELECT 3,'HTML / CSS' WHERE @seed_demo;
INSERT INTO skills (id,name) SELECT 4,'测试与文档' WHERE @seed_demo;
INSERT INTO member_skills (member_id,skill_id,level) SELECT 1,1,4 WHERE @seed_demo;
INSERT INTO member_skills (member_id,skill_id,level) SELECT 1,2,4 WHERE @seed_demo;
INSERT INTO member_skills (member_id,skill_id,level) SELECT 2,3,4 WHERE @seed_demo;
INSERT INTO member_skills (member_id,skill_id,level) SELECT 3,4,4 WHERE @seed_demo;
INSERT INTO projects (id,title,summary,description,status,published,progress,started_on)
SELECT 1,'星河团队项目档案','连接团队、成员、项目与课程成果的协作主页。','采用 PHP、Yii2 与 MySQL，覆盖公开展示、后台编辑、动态发布、留言审核以及课程资料下载。','active',TRUE,80,'2026-10-01' WHERE @seed_demo;
INSERT INTO projects (id,title,summary,description,status,published,progress,started_on)
SELECT 2,'校园数据可视化实验','用轻量图表呈现学习与项目进度。','从结构化数据出发，探索清晰、可访问、可解释的信息展示方式。','completed',TRUE,100,'2026-09-10' WHERE @seed_demo;
INSERT INTO projects (id,title,summary,description,status,published,progress)
SELECT 3,'下一阶段内部草案','未发布项目，不应出现在公开页面。','用于演示后台草稿与公开内容隔离。','planning',FALSE,10 WHERE @seed_demo;
INSERT INTO project_members (project_id,member_id,role) SELECT 1,1,'架构与后端' WHERE @seed_demo;
INSERT INTO project_members (project_id,member_id,role) SELECT 1,2,'界面与交互' WHERE @seed_demo;
INSERT INTO project_members (project_id,member_id,role) SELECT 1,3,'测试与文档' WHERE @seed_demo;
INSERT INTO project_members (project_id,member_id,role) SELECT 2,2,'可视化设计' WHERE @seed_demo;
INSERT INTO milestones (project_id,title,due_on,completed) SELECT 1,'完成需求与数据库设计','2026-10-10',TRUE WHERE @seed_demo;
INSERT INTO milestones (project_id,title,due_on,completed) SELECT 1,'完成前后台核心用户旅程','2026-10-20',FALSE WHERE @seed_demo;
INSERT INTO milestones (project_id,title,due_on,completed) SELECT 2,'完成图表交互原型','2026-09-30',TRUE WHERE @seed_demo;
INSERT INTO updates (id,project_id,author_id,title,body,published,published_at)
SELECT 1,1,1,'星河工坊正式启航','我们将项目过程、团队成员与课程成果汇聚在这里。欢迎浏览项目，也欢迎留下建议！',TRUE,'2026-10-10 09:00:00' WHERE @seed_demo;
INSERT INTO updates (id,project_id,author_id,title,body,published,published_at)
SELECT 2,2,2,'可视化实验阶段完成','完成了项目进度与成员技能的展示实验，下一步将关注访问体验与可维护性。',TRUE,'2026-10-15 14:00:00' WHERE @seed_demo;
INSERT INTO updates (id,project_id,author_id,title,body,published)
SELECT 3,1,1,'内部评审草稿','这是一条未发布的内部动态。',FALSE WHERE @seed_demo;
INSERT INTO messages (id,name,email,body,status)
SELECT 1,'校园访客','visitor@example.test','团队页面很清晰，期待后续项目的展示！','approved' WHERE @seed_demo;
INSERT INTO messages (id,name,email,body,status)
SELECT 2,'学习伙伴','peer@example.test','希望看到更多数据库设计方面的分享。','pending' WHERE @seed_demo;
INSERT INTO message_replies (message_id,author_id,body)
SELECT 1,1,'谢谢关注！我们会持续更新项目进度与实践心得。' WHERE @seed_demo;
INSERT INTO submissions (id,title,kind,file_name,description,sort_order)
SELECT 1,'需求文档','requirements','星河工坊_需求文档(DEMO01_DEMO02_DEMO03).pdf','课程设计需求、范围与验收标准。',1 WHERE @seed_demo;
INSERT INTO submissions (id,title,kind,file_name,description,sort_order)
SELECT 2,'设计文档','design','星河工坊_设计文档(DEMO01_DEMO02_DEMO03).pdf','数据库字典、架构与业务流程。',2 WHERE @seed_demo;
INSERT INTO submissions (id,title,kind,file_name,description,sort_order)
SELECT 3,'实现文档','implementation','星河工坊_实现文档(DEMO01_DEMO02_DEMO03).pdf','实现说明、代码结构与验证结果。',3 WHERE @seed_demo;
INSERT INTO submissions (id,title,kind,file_name,description,sort_order)
SELECT 4,'用户手册','user-manual','星河工坊_用户手册(DEMO01_DEMO02_DEMO03).pdf','前台访问与后台管理操作指南。',4 WHERE @seed_demo;
INSERT INTO submissions (id,title,kind,file_name,description,sort_order)
SELECT 5,'部署文档','deployment','星河工坊_部署文档(DEMO01_DEMO02_DEMO03).pdf','安装、备份与恢复流程。',5 WHERE @seed_demo;
INSERT INTO submissions (id,title,kind,file_name,description,sort_order)
SELECT 6,'项目展示','presentation','星河工坊_项目展示(DEMO01_DEMO02_DEMO03).pdf','课程项目展示与分工说明。',6 WHERE @seed_demo;
INSERT INTO audit_logs (user_id,action,entity_type,entity_id)
SELECT 1,'demo_initialized','team',1 WHERE @seed_demo;
COMMIT;
