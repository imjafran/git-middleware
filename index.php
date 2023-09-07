<?php

namespace WPPOOL\MicroApp;

class GitMiddleware {

	/**
	 * Username
	 *
	 * @var string
	 */
	private $username = 'imjafran';

	/**
	 * Repository
	 *
	 * @var string
	 */
	private $repository = 'git-updater';

	/**
	 * Access token
	 *
	 * @var string
	 */
	private $access_token = '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';


    /**
     * Home
     *
     * @var string
     */
    private $home = 'http://php.local/micro/git-middleware/';

	/**
	 * Fetch git release
	 *
	 * @return mixed
	 */
	public function input( $key, $default = null ) : mixed {
		// return and validate input, sanitize input from $_REQUEST
		return isset( $_REQUEST[ $key ] ) ? (string) $_REQUEST[ $key ] : $default;
	}

	/**
	 * Send response
	 *
	 * @param string $message
	 * @param int    $status
	 * @return void
	 */
	public function send_response( bool $success = true, $message = '' ) : void {
		$response = [
			'success' => $success,
		];

		if ( is_array($message) ) {
			$response = array_merge( $response, $message );
		} else {
			$response['message'] = $message;
		}

		header( 'Content-Type: application/json' );
		echo json_encode( $response );
		exit;
	}

	/**
	 * Initialize middleware
	 *
	 * @return void
	 */
	public function init() : void {
		$action = $this->input( 'action', 'get' );

		$action = 'action_' . $action;
		if ( method_exists( $this, $action ) ) {
			$this->$action();
		} else {
			$this->send_response( false, 'Invalid action' );
		}
	}

	/**
	 * Handle REST actions
	 *
	 * @return void
	 */
	public function action_get() : void {

		$url = sprintf( 'https://api.github.com/repos/%s/%s', $this->username, $this->repository );

		// Set tag
		$tag = $this->input( 'tag' );
		if ( empty( $tag ) || $tag === 'latest' ) {
			$url .= '/releases/latest';
		} else {
			$url .= '/releases/tags/' . $tag;
		}

		// CURL
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_HTTPHEADER, [
			'User-Agent: WPPOOL-App',
			'Authorization: Bearer ' . $this->access_token,
		]);
		// Disable SSL verification (not recommended for production)
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		$response = curl_exec($ch);

		// Check for cURL errors
		if ( curl_errno($ch) ) {
			$this->send_response( false, 'Curl Error: ' . curl_error($ch) );
		}

		curl_close($ch);

		$response = json_decode($response);

        $download_url = sprintf( '%s?action=download&tag=%s', $this->home, $response->tag_name);

		$this->send_response( true, [
			'version' => $response->tag_name,
			'download_url' => $download_url,
		] );
	}

	/**
	 * Action Download
	 */
	public function action_download() {
		$tag = $this->input( 'tag' );

		// If tag is empty
		if ( empty( $tag ) ) {
			$this->send_response( false, 'Tag is required' );
		}

		$download_url = 'https://api.github.com/repos/' . $this->username . '/' . $this->repository . '/zipball/' . $tag;

		// get download using curl and send response as download
		$cr = curl_init( $download_url );
		curl_setopt( $cr, CURLOPT_RETURNTRANSFER, true );
		curl_setopt( $cr, CURLOPT_FOLLOWLOCATION, true );
		curl_setopt( $cr, CURLOPT_SSL_VERIFYPEER, false );
		curl_setopt( $cr, CURLOPT_SSL_VERIFYHOST, false );

		curl_setopt($cr, CURLOPT_HTTPHEADER, [
			'User-Agent: WPPOOL-App',
			'Authorization: Bearer ' . $this->access_token,
		]);

		$download = curl_exec( $cr );
		curl_close( $cr );

		header( 'Content-Type: application/zip' );
		header( 'Content-Disposition: attachment; filename="' . $this->repository . '.zip"' );
		header( 'Content-Length: ' . strlen( $download ) );
		echo $download;

		exit;
	}


}

/**
 * Init middleware
 */
( new GitMiddleware() )->init();
