<?php

/**
 * Description of OpsActions
 *
 * @author jeremy
 */
class OpsActions extends Actions
{
  public static function executePostCommit()
  {
    $payload = json_decode($_REQUEST['payload'], true);
    if ($payload['ref'] === 'refs/heads/master')
    {
      Actions::returnErrorIf(!isset($_SERVER['HTTP_X_HUB_SIGNATURE']), "HTTP header 'X-Hub-Signature' is missing.");

      list($algo, $hash) = explode('=', $_SERVER['HTTP_X_HUB_SIGNATURE'], 2) + array('', '');
      Actions::returnErrorIf(!in_array($algo, hash_algos(), TRUE), 'Invalid hash algorithm "' . $algo . '"');

      $rawPost = file_get_contents('php://input');
      $secret = Config::get('github_key');
      Actions::returnErrorIf($hash !== hash_hmac($algo, $rawPost, $secret), 'Hash does not match. "' . $secret . '"' . ' algo: ' . $algo . '$');

      file_put_contents(ROOT_DIR . '/data/writeable/NEEDS_UPDATE', '');
    }

    return [null, []];
  }

  public static function executeLogUpload()
  {
    $log = isset($_POST['log']) ? urldecode($_POST['log']) : null;
    $name = isset($_POST['name']) ?
            preg_replace('/[^a-z0-9_-]+/', '', substr(strtolower(trim(urldecode($_POST['name']))),0,50)) :
            null;

    Actions::returnErrorIf(!$log || !$name, "Required params: log, name");

    $awsKey = Config::get('aws_log_access_key');
    $awsSecret = Config::get('aws_log_secret_key');

    Actions::returnErrorIf(!$awsKey || !$awsSecret, "Missing AWS credentials");

    $tmpFile = tempnam(sys_get_temp_dir(), 'lbryinstalllog');
    file_put_contents($tmpFile, $log);

    Actions::returnErrorIf(filesize($tmpFile) > 1024*1024*2, "File is too large");

    S3::$useExceptions = true;
    S3::setAuth($awsKey, $awsSecret);
    S3::putObject(S3::inputFile($tmpFile, false), 'lbry-install-logs', $name);
    unlink($tmpFile);

    return [null, []];
  }
}
