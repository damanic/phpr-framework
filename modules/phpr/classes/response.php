<?php namespace Phpr;use Phpr;use Phpr\ValidationException;/** * PHPR Response Class * * This class incapsulates the server respons to a web request. * * The instance of this class is available in the Phpr global object: Phpr::$response. * * @see Phpr */class Response{	const action_on_404_action = 'on_404';	const action_on_exception = 'on_exception';	const controller_application = 'Application';	public static $default_js_scripts = array(		'jquery.js', 		'jquery-ui.js',        'moment.js',		'phpr.js', 		'phpr.indicator.js',		'phpr.request.js',		'phpr.post.js',		'phpr.popup.js',		'phpr.form.js',	);	/**	 * Opens a local URL (like "blog/edit/1")	 * @param string $uri Specifies the URI to open.	 */	public function open($uri)	{		$controller = null;		$action = null;		$parameters = null;		$folder = null;		Phpr::$router->route($uri, $controller, $action, $parameters, $folder);		if ($action == self::action_on_404_action || $action == self::action_on_exception)			$this->open_404();		if (!strlen($controller))			$this->open_404();		$obj = Phpr::$class_loader->load_controller($controller, $folder);		if (!$obj)			$this->open_404();		if ($action == $controller)			$action = 'index';		if (!$obj->_action_exists($action))			$this->open_404();		$obj->_run($action, $parameters);	}	/**	 * Opens the "Page not found" page.	 * By default this method opens a page provided by the PHPR.	 * You may supply the application 404 page by creating the on_404() action in the Application Controller.	 */	public function open_404()	{		// Try to execute the application controller on_404 action.		$controller = Phpr::$class_loader->load_controller(self::controller_application);		if ($controller != null && $controller->_action_exists(self::action_on_404_action))		{			$controller->_run(self::action_on_404_action, array());			die();		}		// Output the default 404 message.		include PATH_SYSTEM."/error_pages/404.htm";		die();	}	/**	 * Opens the Error Page.	 * By default this method opens a page provided by the PHPR.	 * You may supply the application error page by creating the on_exception($exception) action in the Application Controller.	 */	public function open_error_page($exception)	{		if (ob_get_length())			ob_clean();					// Try to execute the application controller on_404 action.		$application = Phpr::$class_loader->load_controller(self::controller_application);				if ($application != null && $application->_action_exists(self::action_on_exception)) 		{			$application->_run(self::action_on_exception, array($exception));			die();		}		$error = Error_Log::get_exception_details($exception);				// Output the default exception message.		include PATH_SYSTEM . "/error_pages/exception.htm";		die();	}	/**	 * Redirects the client browser and terminates the script.	 * This function may send the 'refresh' or 'location' header, depending on the configuration settings.	 * The 'location' header may work incorrectly on Windows servers, but it is faster.	 * To specify the redirect method, set the configuration parameter: 	 * $CONFIG['REDIRECT'] = 'refresh'; or $CONFIG['REDIRECT'] = 'location';	 * By default the location method is used.	 * @param string $uri Specifies the target URI.	 * @param bool $send_301_header Send 301 Moved Permanently HTTP header	 */	public function redirect($uri, $send_301_header = false)	{		if (!Phpr::$request->is_remote_event())		{			if ($send_301_header)				header ('HTTP/1.1 301 Moved Permanently');						switch (Phpr::$config->get("REDIRECT", 'location'))			{				case 'refresh': 					header("Refresh:0;url=".$uri); 					break;								default: 					header("location:".$uri); 					break;			}		}		else		{			$output = "<script>";			$output .= "setTimeout(function(){ window.location='".$uri."'; }, 100);";			$output .= "</script>";			echo $output;		}		die();	}	/**	 * Sends a cookie.	 * @param string $name The name of the cookie. 	 * @param string $value The value of the cookie.	 * @param string $expire The time the cookie expires.	 * @param string $path The path on the server in which the cookie will be available on. 	 * @param string $domain The domain that the cookie is available. 	 * @param string $secure Indicates that the cookie should only be transmitted over a secure HTTPS connection.	 */	public function cookie($name, $value, $expire = 0, $path = '/', $domain = '', $secure = null)	{		$_COOKIE[$name] = $value;		if ($secure === null)		{			if (Phpr::$request->get_protocol() === 'https')				$secure = true;			else 				$secure = false;		}		if (Phpr::$request->is_remote_event())		{			if (post('no_cookies'))				return;						$duration = $expire;			$secure = $secure ? 'true' : 'false';			$output = "<script>";			$output .= "jQuery.cookie('".$name."', '".$value."', {duration: ".$duration.", path: '".$path."', domain: '".$domain."', secure: ".$secure."});";			$output .= "</script>";			echo $output;		} else {						if ($expire > 0)				$expire = time() + $expire*24*3600;							setcookie($name, $value, $expire, $path, $domain, $secure);						// This request may need the up-to-date data			$_COOKIE[$name] = $value; 		}	}		/**	 * Deletes a cookie.	 * @param string $name The name of the cookie. 	 * @param string $path The path on the server in which the cookie will be available on. 	 * @param string $domain The domain that the cookie is available. 	 * @param string $secure Indicates that the cookie should only be transmitted over a secure HTTPS connection.	 */	public function delete_cookie($name, $path = '/', $domain = '', $secure = false)	{		if (Phpr::$request->is_remote_event())		{			if (post('no_cookies'))				return;						$output = "<script type='text/javascript'>";			$output .= "jQuery.cookie('".$name."', null, {duration: 0, path: '".$path."', domain: '".$domain."'});";			$output .= "</script>";			echo $output;		} else		{			setcookie($name, '', time()-360000, $path, $domain, $secure);		}	}	/**	 * Adds <script> section to AJAX response containing a list of JavaScript and CSS 	 * resources which should be loaded before the response text is rendered.	 */	public function add_remote_resources($css = array(), $javascript = array())	{		if (!$css && !$javascript)			return;				$result = '<script>';		$result .= 'var phpr_resource_list_marker = 1;';				if ($css)			$result .= 'phpr_css_list = ["'.implode('","', $css).'"];';		if ($javascript)			$result .= 'phpr_js_list = ["'.implode('","', $javascript).'"];';				$result .= '</script>';				echo $result;	}	/**	 * Outputs a formatted exception, detecting if the request was an AJAX request or not.	 */	public function report_exception($exception)	{		$is_ajax = Phpr::$request->is_ajax();		if($is_ajax)			$this->ajax_report_exception($exception);		else			$this->open_error_page($exception);	}	/**	 * Sends AJAX response with information about exception.	 * @parma mixed $exception Specifies the exception object or message.	 * @param boolean $html Determines whether the response should be in HTML format.	 * @param boolean $focus Determines whether the focusing Java Script code must be added to a response.	 * This parameter will work only if Exception is a Phpr\ValidationException class.	 */	public function ajax_report_exception($exception, $html = false, $focus = false)	{		// Prepare the message		$message = is_object($exception) ? $exception->getMessage() : $exception;		if ($html)			$message = nl2br($message);		// Add focusing Java Script code		if ($focus && $exception instanceof ValidationException)			$message .= $exception->validation->get_focus_error_script();		// Output headers and result		echo "@AJAX-ERROR@";		echo $message;		// Stop the script execution		die();	}	/**	 * This method is used by the PHPR internally.	 * Outputs the requested javascript or css resource.	 */	public static function process_resource_request()	{		// @todo Use a better combine_resources script	}	/**	 * @deprecated	 */ 	public function deleteCookie($name, $path = '/', $domain = '', $secure = false) { Phpr::$deprecate->set_function('deleteCookie', 'delete_cookie'); return $this->delete_cookie($name, $path, $domain, $secure); }	public function ajaxReportException($exception, $html = false, $focus = false) { Phpr::$deprecate->set_function('ajaxReportException', 'ajax_report_exception'); return $this->ajax_report_exception($exception, $html, $focus); }}