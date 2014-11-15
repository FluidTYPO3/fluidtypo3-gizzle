<?php
namespace FluidTYPO3\FluidTYPO3Gizzle\GizzlePlugins;

use NamelessCoder\Gizzle\AbstractPlugin;
use NamelessCoder\Gizzle\Commit;
use NamelessCoder\Gizzle\Payload;
use NamelessCoder\Gizzle\PluginInterface;

/**
 * Class FormalitiesPlugin
 */
class FormalitiesPlugin extends AbstractPlugin implements PluginInterface {

	const MESSAGE_LOWERCASE_SUBJECT_START = 'Commit message uses a lowercase starting letter after the prefix, please use a versal (example: "[TASK] This subject is valid")';
	const MESSAGE_NO_SPACE_AFTER_PREFIX = 'Commit message does not contain a space after the prefix (example: "[TASK] Subject header line")';
	const MESSAGE_INVALIDPREFIX = 'Commit does not start with one of valid prefixes %s';
	const OPTION_VALIDATE_COMMITS = 'commits';

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
		return TRUE;
	}

	/**
	 * @param Payload $payload
	 * @return void
	 */
	public function process(Payload $payload) {
		$hasErrors = FALSE;
		$payload->getResponse()->addOutputFromPlugin($this, array('Starting pull request validation'));
		if (TRUE === $this->getSettingValue(self::OPTION_VALIDATE_COMMITS, TRUE)) {
			$validationResult = $this->validateCommitMessages($payload);
			$hasErrors = (FALSE === $validationResult || FALSE === $hasErrors);
		}
		if (TRUE === $hasErrors && TRUE === $this->pullRequestComesFromGithubWebInterface($payload)) {
			$this->warnAboutErrorsAndPayloadOrigin($payload);
		} elseif (TRUE === $hasErrors) {
			$this->warnAboutErrors($payload);
		} else {
			$this->reportSuccess($payload);
		}
	}

	/**
	 * @param Payload $payload
	 * @return void
	 */
	protected function reportSuccess(Payload $payload) {
		$message = '#### This is an automated comment based on an automated formal commit review.';
		$message .= PHP_EOL . PHP_EOL;
		$message .= 'We salute you for this 100% standards compliant pull request! On behalf of the whole team, thank you for ';
		$message .= 'respecting our coding and contribution guidelines!';
		$this->storePullRequestComment($payload, $message);
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
		$this->storePullRequestComment($payload, $message);
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
		$this->storePullRequestComment($payload, $message);
	}

	/**
	 * @param Payload $payload
	 * @param string $message
	 * @return void
	 */
	protected function storePullRequestComment(Payload $payload, $message) {
		$url = $payload->getPullRequest()->getUrlComments();
		$urlPath = $this->getUrlPathFromUrl($url);
		$parameters = array(
			'body' => $message,
		);
		$payload->getApi()->post($urlPath, json_encode($parameters));
	}

	/**
	 * @param Payload $payload
	 * @param Commit $commit
	 * @param string $message
	 * @return void
	 */
	protected function storeCommitComment(Payload $payload, Commit $commit, $message) {
		$url = $commit->getUrl();
		$urlPath = $this->getUrlPathFromUrl($url);
		$parameters = array(
			'sha1' => $commit->getSha1(),
			'body' => $message
		);
		$payload->getApi()->post($urlPath, json_encode($parameters));
	}

	/**
	 * @param Payload $payload
	 * @param Commit $commit
	 * @param string $message
	 * @return void
	 */
	protected function markCommitErroneous(Payload $payload, Commit $commit, $message) {
		$url = $payload->getPullRequest()->getUrlStatuses();
		$urlPath = $this->getUrlPathFromUrl($url);
		$urlPath = preg_replace('/[a-z0-9]{40}/', $commit->getSha1(), $urlPath);
		$parameters = array(
			'state' => 'failure',
			'description' => $message,
			'context' => 'namelesscoder/gizzle'
		);
		$payload->getApi()->post($urlPath, json_encode($parameters));
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
	 * @return void
	 */
	protected function validateCommitMessages(Payload $payload) {
		$hasErrors = FALSE;
		$url = $payload->getPullRequest()->getUrlCommits();
		$urlPath = $this->getUrlPathFromUrl($url);
		$response = $payload->getApi()->get($urlPath);
		$commits = json_decode($response->getContent(), JSON_OBJECT_AS_ARRAY);
		foreach ($commits as $commitData) {
			$commit = new Commit($commitData['commit']);
			$commit->setUrl($commitData['comments_url']);
			$commit->setId($commitData['sha']);
			$commit->setSha1($commitData['sha']);
			$validationResult = $this->validateCommitMessage($payload, $commit);
			$hasErrors = (FALSE === $validationResult || FALSE === $hasErrors);
		}
		return $hasErrors;
	}

	/**
	 * @param Payload $payload
	 * @param Commit $commit
	 * @return boolean
	 */
	protected function validateCommitMessage(Payload $payload, Commit $commit) {
		$messageResult = $this->commitMessageContainsValidPrefix($payload, $commit);
		if (TRUE !== $messageResult) {
			$payload->getResponse()->addOutputFromPlugin($this, array($messageResult));
			$this->storeCommitComment($payload, $commit, $messageResult);
			$this->markCommitErroneous($payload, $commit, $messageResult);
		}
	}

	/**
	 * @param Payload $payload
	 * @param Commit $commit
	 * @return string|boolean
	 */
	protected function commitMessageContainsValidPrefix(Payload $payload, Commit $commit) {
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
	 * @param Payload $payload
	 * @param Commit $commit
	 * @return string
	 */
	protected function createStatusUpdateUrl(Payload $payload, Commit $commit) {
		return sprintf(
			self::URL_STATUS,
			$payload->getRepository()->getOwner()->getName(),
			$payload->getRepository()->getName(),
			$commit->getId()
		);
	}

	/**
	 * @param string $url
	 * @return string
	 */
	protected function getUrlPathFromUrl($url) {
		$urlPath = substr($url, strpos($url, '/', 9));
		return $urlPath;
	}

}
