<?php
namespace Morbihanet\Modeler;

class JsonLD
{
    const LOCAL_BUSINESS = 0;
    const ARTICLE = 1;
    const EVENT = 2;

    const STRING = 0;
    const DATE = 1;
    const TIME = 2;
    const EMAIL = 3;
    const URL = 4;

    protected $iType = '';
    protected $aJsonLD = null;
    protected $bIsChild = false;

    public function __construct(int $iType, string $strType, bool $bIsChild = false)
    {
        $this->iType = $iType;
        $this->bIsChild = $bIsChild;

        $this->aJsonLD = [
            "@context" => "https://schema.org",
            "@type" => $strType
        ];
    }

    public function setDescription(string $strDescription)
    {
        $this->setProperty("description", $strDescription);
    }

    public function setLocation(string $strName, $latitude, $longitude, string $strMap = '')
    {
        $aLocation = $this->buildLocation($strName, $latitude, $longitude, $strMap);

        if ($aLocation != null) {
            if (isset($this->aJsonLD["location"]) && is_array($this->aJsonLD["location"])) {
                $this->aJsonLD["location"] = array_merge($this->aJsonLD["location"], $aLocation);
            } else {
                $this->aJsonLD["location"] = $aLocation;
            }
        }
    }

    public function addImage(string $strImageURL)
    {
        $aImg = $this->buildImageObject($strImageURL);

        if ($aImg != null) {
            if (isset($this->aJsonLD["image"])) {
                if (isset($this->aJsonLD["image"]["@type"])) {
                    $aFirstImg = $this->aJsonLD["image"];
                    $this->aJsonLD["image"] = array();
                    $this->aJsonLD["image"][] = $aFirstImg;
                }

                $this->aJsonLD["image"][] = $aImg;
            } else {
                $this->aJsonLD["image"] = $aImg;
            }
        }
    }

    public function buildImageObject(string $strURL): array
    {
        $aLogo = null;

        if (file_exists($strURL)) {
            $aSize = getimagesize($strURL);

            if ($aSize) {
                $aLogo = [
                    "@type" => "ImageObject",
                    "url" => $strURL,
                    "width" => $aSize[0],
                    "height" => $aSize[1]
                ];
            }
        }
        return $aLogo;
    }

    public function buildAdress(
        string $strStreet, string $strPostcode, string $strCity, string $strRegion = '', string $strCountry = ''
    ): array {
        $aAdress = ["@type" => "PostalAddress"];

        if (!empty($strStreet)) {
            $aAdress["streetAddress"] = $this->validString($strStreet);
        }

        if (!empty($strPostcode)) {
            $aAdress["postalCode"] = $this->validString($strPostcode);
        }

        if (!empty($strCity)) {
            $aAdress["addressLocality"] = $this->validString($strCity);
        }

        if (!empty($strCountry)) {
            $aAdress["addressCountry"] = $this->validString($strCountry);
        }

        if (!empty($strCountry)) {
            $aAdress["addressRegion"] = $this->validString($strRegion);
        }

        return $aAdress;
    }

    public function buildLocation($strName, $latitude, $longitude, $strMap)
    {
        $aLocation = null;
        $latitude = $this->validString($latitude);
        $longitude = $this->validString($longitude);
        $strMap = $this->validURL($strMap);

        if ((!empty($latitude) && !empty($longitude)) || !empty($strMap)) {
            $aLocation = ["@type" => "Place"];
            $strName = $this->validString($strName);

            if (!empty($strName)) {
                $aLocation["name"] = $strName;
            }

            if (!empty($latitude) && !empty($longitude)) {
                $aLocation['geo'] = [
                    "@type" => "GeoCoordinates",
                    "latitude" => $latitude,
                    "longitude" => $longitude
                ];
            }

            if (!empty($strMap)) {
                $aLocation["hasMap"] = $strMap;
            }
        }

        return $aLocation;
    }

    public function buildContactPoint($strType, $strEMail, $strPhone)
    {
        $aCP = null;
        $strType = $this->validString($strType);
        $strEMail = $this->validString($strEMail);
        $strPhone = $this->validString($strPhone);

        if (!empty($strType)) {
            $aCP = ["@type" => "ContactPoint"];
            $aCP["contactType"] = $strType;

            if (!empty($strEMail)) {
                $aCP["email"] = $strEMail;
            }

            if (!empty($strPhone)) {
                $aCP["telephone"] = $strPhone;
            }
        }

        return $aCP;
    }

    public function setProperty(string $strName, $strValue, string $iType = self::STRING)
    {
        switch ($iType) {
            case static::DATE:
                $strValue = $this->validDate($strValue);
                break;
            case static::TIME:
                $strValue = $this->validTime($strValue);
                break;
            case static::EMAIL:
                $strValue = $this->validEMail($strValue);
                break;
            case static::URL:
                $strValue = $this->validURL($strValue);
                break;
            case static::STRING:
            default:
                $strValue = $this->validString($strValue);
                break;
        }

        if (!empty($strValue)) {
            $this->aJsonLD[$strName] = $strValue;
        }
    }

    public function getHTMLHeadTag(bool $bPrettyPrint = false)
    {
        $strTag = '';

        if (!$this->bIsChild) {
            $strTag = '<script type="application/ld+json">' . PHP_EOL;
            $strTag .= json_encode($this->aJsonLD, $bPrettyPrint ? JSON_PRETTY_PRINT : 0) . PHP_EOL;
            $strTag .= '</script>' . PHP_EOL;
        }

        return $strTag;
    }

    public function getJson($bPrettyPrint = false)
    {
        $strJson = json_encode($this->aJsonLD, $bPrettyPrint ? JSON_PRETTY_PRINT : 0);

        return $strJson;
    }

    public function getObject()
    {
        return $this->aJsonLD;
    }

    protected function validString(string $str): string
    {
        return str_replace("\r", "\n", str_replace("\r\n", "\n", str_replace('"', "'", $str)));
    }

    protected function validDate($date): string
    {
        $strDate = '';

        if ($date != null) {
            if (is_object($date) && get_class($date) == 'DateTime') {
                $uxts = $date->getTimestamp();
            } else if (is_numeric($date)) {
                $uxts = $date;
            } else {
                $uxts = strtotime($date);
            }

            $strTime = date('H:i:s', $uxts);

            if ($strTime === '00:00:00') {
                $strDate = date('Y-m-d', $uxts);
            } else {
                $strDate = date(DATE_ISO8601, $uxts);
            }
        }

        return $strDate;
    }

    protected function validTime(string $strTime): string
    {
        $aTime = explode(':', $strTime);
        $strTime = '';

        if (count($aTime) === 2) {
            $iHour = intval($aTime[0]);
            $iMin = intval($aTime[1]);

            if ($iHour >= 0 && $iHour < 24 && $iMin >= 0 && $iMin < 60) {
                $strTime = sprintf('%02d:%02d', $iHour, $iMin);
            }
        }

        return $strTime;
    }

    protected function validURL(string $strURL): string
    {
        if (!$strURL = filter_var($strURL, FILTER_VALIDATE_URL)) {
            $strURL = '';
        }

        return $strURL;
    }

    protected function validEMail(string $strEMail): string
    {
        if (!$strEMail = filter_var($strEMail, FILTER_VALIDATE_EMAIL)) {
            $strEMail = '';
        }

        return $strEMail;
    }

    static public function strTruncateEllipsis(string $strText, int $iMaxLen, bool $bHardBreak = false): string
    {
        if (strlen($strText) > $iMaxLen - 3 && $iMaxLen > 4) {
            $strText = substr($strText, 0, $iMaxLen - 3);

            if (strrpos($strText, ' ') !== false && !$bHardBreak) {
                $strText = substr($strText, 0, strrpos($strText, ' '));
            }

            $strText .= '...';
        }

        return $strText;
    }
}
