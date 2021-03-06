<?php

class MugoVarnishEvents
{    
    /**
     * ezpEvent listener which sets a vuserhash cookie. Please note: it is NOT SECURE
     * to rely on a client cookie for the user hash. DO NOT USE this feature if you have sensitive
     * data shown on the public siteaccess.
     * 
     * @param string $output
     * @return string
     */
    public static function preoutput( $output )
    {
        self::setUserHashCookie( false );

        return $output;
    }
    
    /**
     * As with the preoutput function, this function is used to cache pages for logged in users
     * 
     * 
     * @param string $oldSession
     * @param string $newSession
     */
    public static function regenerateSession( $oldSession, $newSession )
    {
        self::setUserHashCookie( true );
    }
    
    /**
     * Sets a cookie with the current user hash
     *
     * @param bool $unsetCookie controls whether to remove the cookie
     */
    public static function setUserHashCookie( $unsetCookie = false )
    {
        $wwwDir = eZSys::wwwDir();
        // On host based site accesses this can be empty, causing the cookie to be set for the current dir,
        // but we want it to be set for the whole eZ Publish site
        $cookiePath = $wwwDir != '' ? $wwwDir : '/';

        $ini = eZINI::instance();
        
        if( eZUser::isCurrentUserRegistered() )
        {
            setcookie( 'vuserhash', self::getUserHash(), time() + $ini->variable( 'Session', 'SessionTimeout' ), $cookiePath );
        }
        elseif( $unsetCookie )
        {
            //removes cookie
            setcookie( 'vuserhash', '0', 1, $cookiePath );
        }
    }
    
    /**
     * Gets an instance of the StaticCacheHandler and ask it for the user hash.
     * @throws ezcBasePropertyNotFoundException
     * @throws ezcBaseValueException
     * @return string
     */
    public static function getUserHash()
    {
        $optionArray = array( 'iniFile'      => 'site.ini',
                              'iniSection'   => 'ContentSettings',
                              'iniVariable'  => 'StaticCacheHandler' );

        $options = new ezpExtensionOptions( $optionArray );

        $staticCacheHandler = eZExtension::getHandlerClass( $options );

        return $staticCacheHandler->getUserHash();
    }


    /**
     * Injects X-Location-Id HTTP header for Varnish cache clearing purposes
     * Takes advantage of content/view listener
     * Activated in [Event] block of site.ini
     * Listeners[]=content/view@MugoVarnishEvents::addXLocationIdHeader
     *
     * @param int $nodeID
     * @param eZINI $siteINI
     *
     * @return int
     */
    public static function addXLocationIdHeader($nodeID, eZINI $siteINI)
    {
        header('X-Location-Id: ' . (int)$nodeID);

        return $nodeID;
    }

    /**
     * Activated in [Event] block of site.ini
     * Listeners[]=content/cache/all@MugoVarnishEvents::purgeAll
     */
    public static function purgeAll()
    {
        $staticMugoVarnish = new StaticCacheMugoVarnish();
        $staticMugoVarnish->generateCache(true);
    }

}
