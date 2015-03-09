<?php
namespace FluidTYPO3\FluidTYPO3Gizzle\GizzlePlugins;

use NamelessCoder\Gizzle\AbstractPlugin;
use NamelessCoder\Gizzle\Commit;
use NamelessCoder\Gizzle\Payload;
use NamelessCoder\Gizzle\PluginInterface;
use NamelessCoder\Gizzle\PullRequest;

/**
 * Class FormalitiesPlugin
 */
class FormalitiesPlugin extends AbstractPlugin implements PluginInterface {

	const MESSAGE_LOWERCASE_SUBJECT_START = 'Commit message uses a lowercase starting letter after the prefix, please use a versal (example: "[TASK] This subject is valid")';
	const MESSAGE_NO_SPACE_AFTER_PREFIX = 'Commit message does not contain a space after the prefix (example: "[TASK] Subject header line")';
	const MESSAGE_INVALIDPREFIX = 'Commit does not start with one of valid prefixes %s';
	const OPTION_VALIDATE_COMMITS = 'commits';
	const OPTION_CODE_STYLE = 'codeStyle';
	const OPTION_CODE_STYLE_RULES = 'codeStyleStandard';
	const ACTION_CLOSE = 'closed';
	const PHP_EXTENSION = 'php';

	/**
	 * @var array
	 */
	protected $allowedPrefixes = array('[BUGFIX]', '[FEATURE]', '[TASK]', '[DOC]', '[TER]');

	/**
	 * Analyse $payload and return TRUE if this plugin should
	 * be triggered in processing the payload.
	 *
	 * @param Payload $payload
	 * @return boolean
	 */
	public function trigger(Payload $payload) {
		return (self::ACTION_CLOSE !== $payload->getAction());
	}

	/**
	 * @param Payload $payload
	 * @return void
	 */
	public function process(Payload $payload) {
		$hasErrors = FALSE;
		if (TRUE === $this->getSettingValue(self::OPTION_VALIDATE_COMMITS, TRUE)) {
			$validationResult = $this->validateCommitMessages($payload);
			$hasErrors = (TRUE !== $validationResult || TRUE === $hasErrors);
		}
		if (TRUE === $this->getSettingValue(self::OPTION_CODE_STYLE, TRUE)) {
			$validationResult = $this->validateCodeStyleOfPhpFilesInCommits($payload);
			$hasErrors = (TRUE !== $validationResult || TRUE === $hasErrors);
		}
		if (TRUE === $hasErrors) {
			if (TRUE === $this->pullRequestComesFromGithubWebInterface($payload)) {
				$this->warnAboutErrorsAndPayloadOrigin($payload);
			} else {
				$this->warnAboutErrors($payload);
			}
		}
	}

	/**
	 * @param Payload $payload
	 * @return void
	 */
	protected function warnAboutErrorsAndPayloadOrigin(Payload $payload) {
		$message = '#### This is an automated comment based on an automated formal commit review.';
		$message .= PHP_EOL . PHP_EOL;
		$message .= 'Your pull request contains formal errors and was - it appears - created using the GitHub web interface. ';
		$message .= 'To make the required changes to bring this pull request up to standard, we recommend you clone your forked ';
		$message .= 'repository to a local folder and perform the steps suggested on ';
		$message .= 'https://fluidtypo3.org/documentation/templating-manual/appendix/git-workflow.html#example-the-wrong-commit-message.';
		$message .= PHP_EOL . PHP_EOL;
		$message .= 'Comments have been assigned to each commit in your pull request - please review and adjust. Feel free to ask ';
		$message .= 'for help if you need it!';
		$payload->storePullRequestComment($payload->getPullRequest(), $message);
	}

	/**
	 * @param Payload $payload
	 * @return void
	 */
	protected function warnAboutErrors(Payload $payload) {
		$message = '#### This is an automated comment based on an automated formal commit review.';
		$message .= PHP_EOL . PHP_EOL;
		$message .= 'Your pull request contains formal errors. Comments have been assigned to each commit in your pull request - ';
		$message .= 'please review and adjust. Feel free to ask for help if you need it!';
		$payload->storePullRequestComment($payload->getPullRequest(), $message);
	}

	/**
	 * @param Payload $payload
	 * @param Commit $commit
	 * @param string $message
	 * @param string $status
	 * @return void
	 */
	protected function markCommit(Payload $payload, Commit $commit, $message, $status = 'failure') {
		$url = $payload->getPullRequest()->resolveApiUrl(PullRequest::API_URL_STATUSES);
		$url = preg_replace('/[a-z0-9]{40}/', $commit->getSha1(), $url);
		$parameters = array(
			'state' => $status,
			'description' => $message,
			'context' => 'namelesscoder/gizzle'
		);
		$payload->getApi()->post($url, json_encode($parameters));
	}

	/**
	 * @param Payload $payload
	 * @return boolean
	 */
	protected function pullRequestComesFromGithubWebInterface(Payload $payload) {
		return 0 === strpos($payload->getPullRequest()->getHead()->getRef(), 'patch-');
	}

	/**
	 * @param Payload $payload
	 * @return boolean
	 */
	protected function validateCommitMessages(Payload $payload) {
		$hasErrors = FALSE;
		$url = $payload->getPullRequest()->resolveApiUrl(PullRequest::API_URL_COMMITS);
		$response = $payload->getApi()->get($url);
		$commits = json_decode($response->getContent(), JSON_OBJECT_AS_ARRAY);
		foreach ($commits as $commitData) {
			$commit = new Commit($commitData['commit']);
			$commit->setUrl($commitData['comments_url']);
			$commit->setId($commitData['sha']);
			$commit->setSha1($commitData['sha']);
			$validationResult = $this->validateCommitMessage($payload, $commit);
			$hasErrors = (TRUE !== $validationResult || TRUE === $hasErrors);
		}
		return (FALSE === $hasErrors);
	}

	/**
	 * @param Payload $payload
	 * @param Commit $commit
	 * @return boolean
	 */
	protected function validateCommitMessage(Payload $payload, Commit $commit) {
		$messageResult = $this->commitMessageContainsValidPrefix($commit);
		if (TRUE !== $messageResult) {
			$payload->getResponse()->addOutputFromPlugin($this, array($messageResult));
			$payload->storeCommitComment($commit, $messageResult);
			$this->markCommit($payload, $commit, $messageResult);
			return FALSE;
		}
		return TRUE;
	}

	/**
	 * @param Commit $commit
	 * @return string|boolean
	 */
	protected function commitMessageContainsValidPrefix(Commit $commit) {
		$body = trim($commit->getMessage());
		if (0 === strpos($body, 'Merge pull request') || 0 === strpos($body, 'Merge branch')) {
			return TRUE;
		}
		$hasRequiredPrefix = FALSE;
		foreach ($this->allowedPrefixes as $prefix) {
			if (0 === strpos($body, $prefix)) {
				$hasRequiredPrefix = TRUE;

				$trimmed = substr($body, strlen($prefix));
				$firstCharacter = substr($trimmed, 0, 1);
				if (' ' !== $firstCharacter) {
					return self::MESSAGE_NO_SPACE_AFTER_PREFIX;
				} else {
					$firstCharacter = substr($trimmed, 1, 1);
				}
				if (strtoupper($firstCharacter) !== $firstCharacter) {
					return self::MESSAGE_LOWERCASE_SUBJECT_START;
				}
				break;
			}
		}
		if (FALSE === $hasRequiredPrefix) {
			return sprintf(self::MESSAGE_INVALIDPREFIX, implode(', ', $this->allowedPrefixes));
		}
		return TRUE;
	}

	/**
	 * @param string $url
	 * @return string
	 */
	protected function getUrlPathFromUrl($url) {
		$urlPath = substr($url, strpos($url, '/', 9));
		return $urlPath;
	}

	/**
	 * @param Payload $payload
	 * @return boolean
	 */
	protected function validateCodeStyleOfPhpFilesInCommits(Payload $payload) {
		$url = $payload->getPullRequest()->resolveApiUrl(PullRequest::API_URL_COMMITS);
		$response = $payload->getApi()->get($url);
		$commits = json_decode($response->getContent(), JSON_OBJECT_AS_ARRAY);
		$hasErrors = FALSE;
		foreach ($commits as $commitData) {
			$commitUrl = $commitData['url'];
			$commitResponse = $payload->getApi()->get($commitUrl);
			$commitData = json_decode($commitResponse->getContent(), JSON_OBJECT_AS_ARRAY);
			$commit = new Commit($commitData);
			$commit->setId($commitData['sha']);
			foreach ($commitData['files'] as $fileData) {
				$extension = pathinfo($fileData['filename'], PATHINFO_EXTENSION);
				if (self::PHP_EXTENSION === $extension) {
					$result = $this->validateCodeStyleOfPhpFile($payload, $commit, $fileData['raw_url'], $fileData['filename']);
					$hasErrors = (TRUE !== $result || TRUE === $hasErrors);
				}
			}
		}
		return (FALSE === $hasErrors);
	}

	/**
	 * @param Payload $payload
	 * @param Commit $commit
	 * @param string $url
	 * @return boolean
	 */
	protected function validateCodeStyleOfPhpFile(Payload $payload, Commit $commit, $url, $path) {
		$contents = file_get_contents($url);
		$syntaxCommand = 'php -l';
		list ($result, $errors) = $this->passStdinToCommand($syntaxCommand, $contents);
		if (FALSE === empty($errors)) {
			$errors = trim($errors);
			$errors = str_replace('parse error in - on line', 'parse error in ' . $path . ' on line', $errors);
			$payload->storeCommitValidation($payload->getPullRequest(), $commit, $errors, $path, substr($errors, 0, strrpos($errors, ' ')));
			$this->markCommit($payload, $commit, 'PHP syntax check failed! ' . $errors);
			return FALSE;
		}
		$codeStyleCommand = 'vendor/bin/phpcs --standard=' . $this->getSettingValue(self::OPTION_CODE_STYLE_RULES) . ' --report=json';
		list ($result, $errors) = $this->passStdinToCommand($codeStyleCommand, $contents);
		if (FALSE === empty($errors)) {
			$payload->getResponse()->addOutputFromPlugin($this, array('Error running PHPCS: ' . $errors));
			return FALSE;
		}
		$validation = json_decode($result, JSON_OBJECT_AS_ARRAY);
		$errorsAndWarnings = (integer) $validation['totals']['errors'] + (integer) $validation['totals']['warnings'];
		if (0 === $errorsAndWarnings) {
			$this->markCommit($payload, $commit, 'No parsing errors and coding style is valid', 'success');
			return TRUE;
		} else {
			$messages = $validation['files']['STDIN']['messages'];
			foreach ($messages as $messageData) {
				$payload->storeCommitValidation($payload->getPullRequest(), $commit, $messageData['message'], $path, $messageData['line']);
				$this->markCommit($payload, $commit, 'Commit has one or more coding standards violations');
			}
			return FALSE;
		}
	}

	/**
	 * @param string $command
	 * @param string $input
	 * @return array
	 */
	protected function passStdinToCommand($command, $input) {
		$descriptors = array(
			array('pipe', 'r'),
			array('pipe', 'w'),
			array('pipe', 'w'),
		);
		$pipes = array();
		$proc = proc_open($command, $descriptors, $pipes, GIZZLE_HOME);
		fwrite($pipes[0], $input);
		fclose($pipes[0]);
		$result = stream_get_contents($pipes[1]);
		$errors = stream_get_contents($pipes[2]);
		fclose($pipes[1]);
		fclose($pipes[2]);
		return array($result, $errors);
	}

}
