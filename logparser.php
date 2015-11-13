#!/usr/bin/php
<?php


# command-line or server line-break output
define('LINE_BREAK', (PHP_SAPI === 'cli') ? "\n" : '<br>');


# command-line usage
if (PHP_SAPI === 'cli')
{
    if (@ ! $_SERVER['argv'][1]) 
    {
        $sUsage = "\n " . basename($_SERVER['argv'][0], '.php') . "\n\n\tusage: " . basename($_SERVER['argv'][0], '.php') . " <filename>\n\n";
        die($sUsage);
    }

    $sFile = $_SERVER['argv'][1];
}
# web server usage, file in same directory as this file
else
{
    $sFile = 'access.log';
}


if ( ! file_exists($sFile))
{
    die(LINE_BREAK . $sFile . ' does not exist in this directory!' . LINE_BREAK . LINE_BREAK);
}
else
{
    $oLP = new LogParser($sFile);
    echo $oLP->generateReport();
}



##################################################################################


class LogParser
{
    /**
        * Apache log file parser.
        *
        * Coded for PHP 5.4+
        * Tested on Debian, CentOS, and Windows (XAMPP) Apache log files.
        *
        * Example usage:
        *
        *                php -f logparser.php /var/log/apache2/access.log
        *                php -f logparser.php /var/log/httpd/access_log
        *                php -f logparser.php C:\XAMPP\apache\logs\access.log
        *
        *                or add access.log file into web directory and run server
        *
        * @author        Martin Latter <copysense.co.uk>
        * @copyright     Martin Latter 02/09/2015
        * @version       0.2
        * @license       GNU GPL version 3.0 (GPL v3); http://www.gnu.org/licenses/gpl.html
        * @link          https://github.com/Tinram/Log-Parser.git
    */


    const NUM_TOP_ITEMS = 10;

    private $aUserAgentList = ['firefox', 'trident', 'webkit']; # define common browsers (basic list - add more here); Safari and Chrome are both 'webkit'
    private $iCount = 0;
    private $iParseCount = 0;
    private $iHTTPErrors = 0;
    private $iHTTPSuccess = 0;
    private $aAccessedFiles = [];
    private $aReferrers = [];
    private $aUserAgents = [];
    private $rxPattern = '/^([^ ]+) ([^ ]+) ([^ ]+) (\[[^\]]+\]) "(.*) (.*) (.*)" ([0-9\-]+) ([0-9\-]+) "(.*)" "(.*)"$/'; # credits: David Sklar and Adam Trachtenberg


    public function __construct($sFile)
    {
        $this->processFile($sFile);
    }


    public function __destruct() {}


    /**
        * Process log file.
        *
        * @param   string $sFile, filename
    */

    private function processFile($sFile)
    {
        $aMatches = [];

        # get file handle
        $rFH = fopen($sFile, 'r'); 

        # terminate on file handle failure
        if ($rFH === false)
        {
            die('Could not open log file! (' . $sFile . ')');
        }

        while ( ! feof($rFH))
        {
            if ($sLine = trim(fgets($rFH, 1024)))
            {
                if (preg_match($this->rxPattern, $sLine, $aMatches))
                {
                    # HTTP general status code count
                    $sResponseDigit = substr($aMatches[8], 0, 1);

                    if ($sResponseDigit === '2' || $sResponseDigit === '3')
                    {
                        $this->iHTTPSuccess++;
                    }
                    else if ($sResponseDigit === '4' || $sResponseDigit === '5')
                    {
                        $this->iHTTPErrors++;
                    }

                    # accessed file count
                    if (isset($this->aAccessedFiles[$aMatches[6]]))
                    {
                        $this->aAccessedFiles[$aMatches[6]]++;
                    }
                    else
                    {
                        $this->aAccessedFiles[$aMatches[6]] = 1;
                    }

                    # referrer count
                    if (isset($this->aReferrers[$aMatches[10]]))
                    {
                        $this->aReferrers[$aMatches[10]]++;
                    }
                    else
                    {
                        $this->aReferrers[$aMatches[10]] = 1;
                    }

                    # user agent count
                    $sUserAgent = strtolower($aMatches[11]);

                    foreach ($this->aUserAgentList as $sBrowser)
                    {
                        if (stripos($sUserAgent, $sBrowser) !== false)
                        {
                            if (isset($this->aUserAgents[$sBrowser]))
                            {
                                $this->aUserAgents[$sBrowser]++;
                            }
                            else
                            {
                                $this->aUserAgents[$sBrowser] = 1;
                            }
                        }
                    }

                    $this->iParseCount++;
                }
                else
                {
                    echo 'Parse failure at line' . $this->iCount . ': ' . $sLine . LINE_BREAK;
                }
            }

            $this->iCount++;
        }

        fclose($rFH);

    } # end parseFile()


    /**
        * Log file statistics output.
        *
        * @return  string
    */

    public function generateReport()
    {
        $sReport = '';

        $sReport .= LINE_BREAK . 'Lines parsed: ' . $this->iCount . LINE_BREAK;
        $sReport .= 'Correctly parsed log file entries: ' . $this->iParseCount . LINE_BREAK . LINE_BREAK;
        $sReport .= 'HTTP success codes: ' . $this->iHTTPSuccess . LINE_BREAK;
        $sReport .= 'HTTP error codes: ' . $this->iHTTPErrors . LINE_BREAK;

        $sReport .= $this->processFieldCount($this->aAccessedFiles, 'Top accessed files (hits)');
        $sReport .= $this->processFieldCount($this->aReferrers, 'Top referrers');
        $sReport .= $this->processFieldCount($this->aUserAgents, 'Top user agents', true);

        $sReport .= LINE_BREAK;

        return $sReport;

    } # end generateReport()


    /**
        * Parse, sort, process array of counted fields.
        *
        * @param   array $aItems, array(field => count)
        * @param   string $sDescription, message
        * @param   boolean $bPercentReq, percentage output toggle
        *
        * @return  string
    */

    private function processFieldCount(array $aItems, $sDescription = 'none', $bPercentReq = false)
    {
        arsort($aItems);

        if (isset($aItems['-']))
        {
            unset($aItems['-']);
        }

        # extract top items and use for output
        $aItems = array_slice($aItems, 0, self::NUM_TOP_ITEMS);

        $sOut = LINE_BREAK . $sDescription . ':' . LINE_BREAK;

        foreach ($aItems as $sKey => $sNum)
        {
            $sOut .= $sKey . ' : ' . $sNum;

            if ($bPercentReq)
            {
                $fValue = ( ( ((int) $sNum) / $this->iParseCount) * 100 );
                $sOut .= ' (' . sprintf('%01.2f', $fValue) . ' %)';
            }

            $sOut .= LINE_BREAK;
        }

        return $sOut;

    } # end processFieldCount()

} # end {}
