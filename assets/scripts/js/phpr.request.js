(function($) {

	PHPR.request = function(url, handler, options) {
		var o = {},
			_deferred = $.Deferred(),
			_url = url,
			_handler = handler,
			_options = options,
			_locked = false;
		
		o.text = '';
		o.html = '';
		o.javascript = '';
		o.error = '';
		o.status = '';

		o.getDefaultOptions = function() {
			return {
				data: {},
				update: {},
				lock: true
			};
		}
		
		o.getOptions = function() {
			var options = $.extend(true, o.getDefaultOptions(), _options);
			return options;
		}

		o.sendRequest = function() {
			if (_locked)
				return;

			var ajax = _get_ajax_object(),
				options = o.getOptions();

			// On Complete
			ajax.always(function(){
				_locked = false;
			});

			// On Success
			ajax.done(function(data) {
				o.parseResponse(data);

				if (o.isSuccess()) {
					_deferred.resolve(o);
				} else {
					o.error = o.html.replace('@AJAX-ERROR@', '');
					o.status = 'error';
					_deferred.reject(o);
				}
			});

			// On Failure
			ajax.fail(function(data, status, message) {
				o.parseResponse(data);
				o.error = message;
				o.status = status;
				_deferred.reject(o);
			});

			if (options.lock)
				_locked = true;

			return false;
		}

		o.isSuccess = function() {
			return o.text.search('@AJAX-ERROR@') == -1;
		}

		o.parseResponse = function(data) {
			o.text = data;

			if (typeof(o.text) !== 'string')
				o.text = '';

			o.html = _strip_scripts(o.text, function(javascript){
				o.javascript = javascript;
			});
		}

		var _get_ajax_object = function() {
			
			var options = o.getOptions(),
				ajaxObj = {
					url: _url,
					type: 'POST',
					dataType: 'html', // Always force plaintext
					beforeSend: function(xhr) {
						xhr.setRequestHeader('PHPR-REMOTE-EVENT', '1');
						xhr.setRequestHeader('PHPR-POSTBACK', '1');
						xhr.setRequestHeader('PHPR-EVENT-HANDLER', 'ev{on_handle_request}');
					},
					data: {
						cms_handler_name: _handler
					}
				};

			ajaxObj.data = $.extend(true, ajaxObj.data, options.data);

			if (options.update)
				ajaxObj.data.cms_update_elements = options.update;

			return $.ajax(ajaxObj);
		}

		var _strip_scripts = function(data, option) {
			var scripts = '';

			var text = data.replace(/<script[^>]*>([^\b]*?)<\/script>/gi, function() {
				scripts += arguments[1] + '\n';
				
				return '';
			});

			if (option === true)
				eval(scripts);
			else if (typeof(option) == 'function')
				option(scripts, text);
			
			return text;
		}		

		// Send the request on construct
		o.sendRequest();

		// Promote the request object with a promise
		return _deferred.promise(o);
	}

})(jQuery);