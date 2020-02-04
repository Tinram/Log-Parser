<?php

declare(strict_types = 1);


# command-line or server line-break output
define('LINE_BREAK', (PHP_SAPI === 'cli') ? PHP_EOL : '<br>');


# command-line usage
if (PHP_SAPI === 'cli')
{
    if ( ! isset($_SERVER['argv'][1]))
    {
        $sUsage = LINE_BREAK .
        ' ' . basename(__FILE__, '.php') .
        LINE_BREAK . LINE_BREAK .
        "\tusage: php " . basename(__FILE__) . ' <filename>' . LINE_BREAK . LINE_BREAK;
        die($sUsage);
    }

    $sFile = $_SERVER['argv'][1];
}
else # web server usage, file in same directory as this file
{
    $sFile = 'access.log';
}


if ( ! file_exists($sFile))
{
    die(LINE_BREAK . ' \'' . $sFile . '\' does not exist in this directory!' . LINE_BREAK . LINE_BREAK);
}
else
{
    $oLP = new LogParser($sFile);
    echo $oLP->generateReport();
}


##################################################################################


final class LogParser
{
    /**
        * Apache log file parser.
        *
        * Coded for PHP 7.2
        * Tested on Debian, CentOS, and Windows (XAMPP) Apache log files.
        *
        * Example usage:
        *
        *                php logparser.php /var/log/apache2/access.log
        *                php logparser.php /var/log/httpd/access_log
        *                php logparser.php C:\XAMPP\apache\logs\access.log
        *
        *                or add access.log file into web directory and run server
        *
        * @author        Martin Latter
        * @copyright     Martin Latter 02/09/2015
        * @version       0.24
        * @license       GNU GPL version 3.0 (GPL v3); http://www.gnu.org/licenses/gpl.html
        * @link          https://github.com/Tinram/Log-Parser.git
    */


    /** @const string NUM_TOP_ITEMS */
    const NUM_TOP_ITEMS = 10;

    /** @var array<string> $aUserAgentList, browsers */
    private $aUserAgentList = ['firefox', 'trident', 'webkit']; # define common browsers (basic list - add more here in lowercase); Safari and Chrome are both 'webkit'

    /** @var integer $iCount */
    private $iCount = 0;

    /** @var integer $iParseCount */
    private $iParseCount = 0;

    /** @var integer $iHTTPErrors */
    private $iHTTPErrors = 0;

    /** @var integer $iHTTPSuccess */
    private $iHTTPSuccess = 0;

    /** @var array<integer> $aAccessedFiles */
    private $aAccessedFiles = [];

    /** @var array<integer> $aReferrers */
    private $aReferrers = [];

    /** @var array<integer> $aUserAgents */
    private $aUserAgents = [];

    /** @var string $sLineBreak */
    private $sLineBreak = '';

    /** @var string $sTab */
    private $sTab = '';

    /** @var string $rxPattern */
    private $rxPattern = '/^([^ ]+) ([^ ]+) ([^ ]+) (\[[^\]]+\]) "(.*) (.*) (.*)" ([0-9\-]+) ([0-9\-]+) "(.*)" "(.*)"$/'; # regex credits: David Sklar and Adam Trachtenberg


   /**
        * Constructor.
        *
        * @param   string $sFile
    */

    public function __construct(string $sFile)
    {
        $this->sLineBreak = (PHP_SAPI === 'cli') ? PHP_EOL : '<br>';
        $this->sTab = (PHP_SAPI === 'cli') ? "\t" : str_repeat('&nbsp;', 4);
        $this->processFile($sFile);
    }


    /**
        * Process log file.
        *
        * @param   string $sFile, filename
        *
        * @return  void
    */

    private function processFile(string $sFile): void
    {
        $aMatches = [];

        # get file handle
        $rFH = fopen($sFile, 'r'); 

        # terminate on file handle failure
        if ($rFH === false)
        {
            die('Could not open log file! (' . $sFile . ')');
        }

        while (($sLine = fgets($rFH, 1024)) !== false)
        {
            $sLine = trim($sLine);

            if (preg_match($this->rxPattern, $sLine, $aMatches) === 1)
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
                    if (strpos($sUserAgent, $sBrowser) !== false)
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
                echo 'Regex parse failure at line ' . ($this->iCount + 1) . ': ' . $sLine . $this->sLineBreak;
            }

            $this->iCount++;
        }

        fclose($rFH);
    }


    /**
        * Log file statistics output.
        *
        * @return  string
    */

    public function generateReport(): string
    {
        $sReport = '';

        $sReport .= $this->sLineBreak . 'Lines parsed: ' . $this->iCount . $this->sLineBreak;
        $sReport .= 'Correctly parsed log file entries: ' . $this->iParseCount . $this->sLineBreak . $this->sLineBreak;
        $sReport .= 'HTTP success codes: ' . $this->iHTTPSuccess . $this->sLineBreak;
        $sReport .= 'HTTP error codes: ' . $this->iHTTPErrors . $this->sLineBreak;

        $sReport .= $this->processFieldCount($this->aAccessedFiles, 'Top accessed files (hits)');
        $sReport .= $this->processFieldCount($this->aReferrers, 'Top referrers');
        $sReport .= $this->processFieldCount($this->aUserAgents, 'Top user agents', true);

        $sReport .= $this->sLineBreak;

        return $sReport;
    }


    /**
        * Parse, sort, process array of counted fields.
        *
        * @param   array<mixed> $aItems, array(field => count)
        * @param   string $sDescription, message
        * @param   boolean $bPercentReq, percentage output toggle
        *
        * @return  string
    */

    private function processFieldCount(array $aItems, string $sDescription = 'none', bool $bPercentReq = false): string
    {
        arsort($aItems);

        if (isset($aItems['-']))
        {
            unset($aItems['-']);
        }

        # extract top items and use for output
        $aItems = array_slice($aItems, 0, self::NUM_TOP_ITEMS);

        $sOut = $this->sLineBreak . $sDescription . ':' . $this->sLineBreak;

        foreach ($aItems as $sKey => $sNum)
        {
            $sOut .= sprintf('%4d' . $this->sTab . '%s', $sNum, $sKey);

            if ($bPercentReq)
            {
                $fValue = ( ( ((int) $sNum) / $this->iParseCount) * 100 );
                $sOut .= ' (' . sprintf('%01.2f', $fValue) . ' %)';
            }

            $sOut .= $this->sLineBreak;
        }

        return $sOut;
    }
}
