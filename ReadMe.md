
# Log Parser

#### Simple Apache server log file analyser.


## Purpose

Parse server log files and display top count statistics (i.e. files, referrers).

Runs as either a command-line tool or through the local server.


## Usage

### Linux

#### Debian-based

```bash
    php logparser.php /var/log/apache2/access.log
```

#### CentOS

```bash
    php logparser.php /var/log/httpd/access_log
```

If the script file is renamed, made executable, and placed in a *$PATH* such as `/usr/local/bin`:

```bash
    logparser /var/log/apache2/access.log
```


### Windows

```batch
    php logparser.php C:\XAMPP\apache\logs\access.log
```


## License

Log Parser is released under the [GPL v.3](https://www.gnu.org/licenses/gpl-3.0.html).
