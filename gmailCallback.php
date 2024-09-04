<?php

define('ROOT_DIRECTORY', __DIR__ !== DIRECTORY_SEPARATOR ? __DIR__ : '');

require __DIR__ . '/include/main/WebUI.php';

\App\Process::$requestMode = 'GmailCallback';
try {
  $request = \App\Request::init();
  App\Session::init();

  $domain = \App\Request::_get('domain') ?: \App\Session::get('oauth2domain');
  if (!$domain) {
    echo 'Could not find OAuth2 client id and secret';
    \App\Log::error('No domain found');
    header('location: ' . \App\Config::main('site_URL'), true, 301);
    exit;
  }

  $configs = \App\Config::module('OSSMail', 'oauth_configs', []);
  $config = $configs[$domain];

  if (!$config) {
    echo 'Could not find OAuth2 client id and secret';
    \App\Log::error('No config found');
    header('location: ' . \App\Config::main('site_URL'), true, 301);
    exit;
  }

  $providerName = 'google';
  $clientId = $config['client_id']; 
  $clientSecret = $config['client_secret'];

  $params = [
      'clientId' => $clientId,
      'clientSecret' => $clientSecret,
      'urlAuthorize' => \App\Config::module('OSSMail', 'oauth_auth_uri'),
      'urlAccessToken' => \App\Config::module('OSSMail', 'oauth_token_uri'),
      'urlResourceOwnerDetails' => \App\Config::module('OSSMail', 'oauth_identity_uri'),
      'redirectUri' => \App\Config::main('site_URL') . 'gmailCallback.php',
      'accessType' => 'offline',
  ];

  $options = [];

  $provider = new \League\OAuth2\Client\Provider\GenericProvider($params);
  $options = [
      'scope' => [
          'https://mail.google.com/'
      ],
      'access_type' => 'offline',
      'prompt' => 'consent',
      'approval_prompt' => null,
  ];

  if (!isset($_GET['code'])) {
    //If we don't have an authorization code then get one
    \App\Session::set('oauth2domain', $domain);
    \App\Session::set('oauth2state', $provider->getState());
    $authUrl = $provider->getAuthorizationUrl($options);
    header('Location: ' . $authUrl);
    exit;
    //Check given state against previously stored one to mitigate CSRF attack
  } elseif (empty($_GET['state']) || ($_GET['state'] !== \App\Session::get('oauth2state'))) {
    \App\Session::delete('oauth2state');
    exit('Invalid state');
  } else {
    //Try to get an access token (using the authorization code grant)
    $token = $provider->getAccessToken(
        'authorization_code',
        [
            'code' => $_GET['code']
        ]
    );
    \App\Log::warning(var_export($token, true));
    //Use this to get a new access token if the old one expires
    echo 'Refresh Token: ', htmlspecialchars($token->getRefreshToken());
  }
} catch (Exception $e) {
	\App\Log::error($e->getMessage() . ' => ' . $e->getFile() . ':' . $e->getLine());
	header('location: ' . \App\Config::main('site_URL'), true, 301);

  echo "Authorization failed";
}
