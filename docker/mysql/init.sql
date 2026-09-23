-- Runs once when the MySQL volume is first created.
-- Lets the app user create and use the test database (myoils_test) next to the main one.
GRANT ALL PRIVILEGES ON `myoils%`.* TO 'app'@'%';
FLUSH PRIVILEGES;
