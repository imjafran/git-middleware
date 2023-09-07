<?php 
## phpcs:ignoreFile

class GitMiddleware {

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
     * @param int $status
     * @return void
     */
    public function send_response( bool $success = true, $message = '' ) : void {
        $response = [
            'success' => $success
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
     * HTTP request
     *s
     * @param string $url URL (optional)
     * @param string|null $bearer_token Bearer token (optional)
     * @return mixed
     */
    function request( string $url = '', $bearer_token = null) {
        // Initialize cURL session
        $ch = curl_init();

        // Set the cURL options
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        // If a bearer token is provided, set the Authorization header
        if ($bearer_token !== null) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'User-Agent: WPPOOL-App',
                'Authorization: Bearer ' . $bearer_token
            ]);
        }

        // Disable SSL verification (not recommended for production)
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        // Execute the cURL request and capture the response
        $response = curl_exec($ch);

        // Check for cURL errors
        if (curl_errno($ch)) {
            // Handle error, e.g., return an error response or throw an exception
            return (object) ['error' => 'Curl Error: ' . curl_error($ch)];
        }

        // Close cURL session
        curl_close($ch);

        // Return the HTTP response as-is (you may want to parse it if it's JSON, XML, etc.)
        return json_decode($response);
    }

    /**
     * Handle REST actions
     *
     * @return void
     */
    public function action_get() : void {
        
        // check username, repository; required
        if ( ! $this->input( 'username' ) || ! $this->input( 'repository' ) ) {
            $this->send_response( false, 'Username and repository required' );
        }

        $username = $this->input( 'username' );
        $repository = $this->input( 'repository' );
        $access_token = $this->input( 'access_token' );

        $git_url = 'https://api.github.com/repos/' . $username . '/' . $repository;

        $tag = $this->input( 'tag' );

        if ( $tag ) {
            $git_url .= '/releases/tags/' . $tag;
        } else {
            $git_url .= '/releases/latest';
        }

        // get git info
        $git_info = $this->request( $git_url, $access_token );

        // if any error
        if ( isset( $git_info->error ) ) {
            $this->send_response( false, $git_info->error );
        }

        $download_url = 'http://php.local/micro/git-middleware/?action=download&username=' . $username . '&repository=' . $repository . '&access_token=' . $access_token . '&tag=' . $git_info->tag_name;
        
        $this->send_response( true, [
            'version' => $git_info->tag_name,
            'download_url' => $download_url,
        ] );
    }

    /**
     * Action Download
     */
    public function action_download() {
        // check username, repository; required
        if ( ! $this->input( 'username' ) || ! $this->input( 'repository' ) || ! $this->input( 'tag' ) ) {
            $this->send_response( false, 'Username, repository and tag required' );
        }

        $username = $this->input( 'username' );
        $repository = $this->input( 'repository' );
        $access_token = $this->input( 'access_token' );
        $tag = $this->input( 'tag' );

        $download_url = 'https://api.github.com/repos/' . $username . '/' . $repository . '/zipball/' . $tag;

        // get download using curl and send response as download
        $cr = curl_init( $download_url );
        curl_setopt( $cr, CURLOPT_RETURNTRANSFER, true );
        curl_setopt( $cr, CURLOPT_FOLLOWLOCATION, true );
        curl_setopt( $cr, CURLOPT_SSL_VERIFYPEER, false );
        curl_setopt( $cr, CURLOPT_SSL_VERIFYHOST, false );

        curl_setopt($cr, CURLOPT_HTTPHEADER, [
            'User-Agent: WPPOOL-App',
            'Authorization: Bearer ' . $access_token
        ]);

        $download = curl_exec( $cr );
        curl_close( $cr );

        header( 'Content-Type: application/zip' );
        header( 'Content-Disposition: attachment; filename="' . $repository . '.zip"' );
        header( 'Content-Length: ' . strlen( $download ) );
        echo $download;

        exit;
    }


}

/**
 * Init middleware
 */
(new GitMiddleware())->init();
