<?php

class Trustly_Api_Unsigned extends Trustly_Api {
	/* Login criterias when using the unsigned API. Only used by the 
	 * newSessionCookie() call which is called automatically before the 
	 * first call */
	var $api_username = NULL;
	var $api_password = NULL;

	var $session_uuid = NULL;

	public function __construct($username, $password, $host='trustly.com', $port=443, $is_https=TRUE) {
		parent::__construct($host, $port, $is_https);

		$this->api_username = $username;
		$this->api_password = $password;
	}

	public function urlPath($request=NULL) {
		return '/api/Legacy';
	}

	public function handleResponse($request, $body, $curl) {
			/* No signature here, just build the response object */
        return new Trustly_Data_JSONRPCResponse($body, $curl);
	}

	public function insertCredentials($request) {
		$request->setParam('Username', $this->api_username);
		if(isset($this->session_uuid)) {
			$request->setParam('Password', $this->session_uuid);
		} else {
			$request->setParam('Password', $this->api_password);
		}
		return TRUE;
	}

	protected function hasSessionUUID() {
		return (bool)isset($this->session_uuid);
	}

	/* Call NewSessionCookie to obtain a session cookie we can use for the rest 
	 * of our calls. This is automatically called when doing a call if we do 
	 * not have a session. Call manually if needed at session timeout etc. 
	 * */
	public function newSessionCookie() {
		$this->session_uuid = NULL;

		$request = new Trustly_Data_JSONRPCRequest('NewSessionCookie');
			/* Call parent directly here as we will attempt to detect the 
			 * missing session uuid here and call this function if it is not set */
		$response = parent::call($request);

		if(isset($response)) {
			if($response->isSuccess()) {
				$this->session_uuid = $response->getResult('sessionuuid');
			}
		}
		if(!isset($this->session_uuid)) {
			throw new Trustly_AuthentificationException();
		}
		return $response;
	}

	/* Utility wrapper around a call() to GetViewStable to simply getting data 
	 * from a view. */
	public function getViewStable($viewname, $dateorder=NULL, $datestamp=NULL, 
		$filterkeys=NULL, $limit=100, $offset=0, $params=NULL, $sortby=NULL, 
		$sortorder=NULL) {

		return $this->call('GetViewStable', array(
			DateOrder => $dateorder,
			Datestamp => $datestamp,
			FilterKeys => $filterkeys,
			Limit => $limit,
			Offset => $offset,
			Params => $params,
			SortBy => $sortby,
			SortOrder => $sortorder,
			ViewName => $viewname,
		));
	}

	/* Issue an unsigned API call. As the unsigned API contains a huge array of 
	 * functions we will use the call() method directly for the majority of 
	 * operations. The data in params will be matched into the parameters of 
	 * the outgoing call. Take care when supplying the arguments for the call 
	 * so they match the function prototype properly. */
	public function call($method, $params=NULL)  {
		$request = new Trustly_Data_JSONRPCRequest($method);

		if(isset($params)) {
			foreach($params as $key => $value) {
				$request->setParam($key, $value);
			}
		}

		if(!$this->hasSessionUUID()) {
			$this->newSessionCookie();
		}

		return parent::call($request);
	}
}
?>