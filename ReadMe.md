
# Log Parser


## Purpose

Parse Apache server log files and display top count statistics (i.e. files, referrers).

Runs as either a command-line tool or through the local server.


## Usage

### Linux

`php -f logparser.php /var/log/apache2/access.log` # Debian-based

`php -f logparser.php /var/log/httpd/access_log` # CentOS

or, if the file is renamed and placed in `/usr/local/bin`

`logparser /var/log/apache2/access.log`


### Windows

`php -f logparser.php C:\XAMPP\apache\logs\access.log`


## License

Log Parser is released under the [GPL v.3](https://www.gnu.org/licenses/gpl-3.0.html).
