/*
 * Image uploader for the page editor's image regions (admin/editors/image.php).
 *
 * Replaces Uploadify 3.1, which needed Flash - no browser has run Flash since
 * January 2021. It takes the options the editor gave Uploadify and calls the
 * same callbacks, so the editor's code is unchanged:
 *
 *   uploader         URL the file is posted to (as "Filedata", like Uploadify)
 *   buttonText       label of the button
 *   onUploadSuccess  function(file, data, response) - data is the page's reply
 *   onUploadError    function(file, errorCode, errorMsg, errorString)
 *
 * The upload is an ordinary browser request, so it carries the login cookie.
 */
(function ($) {
	$.fn.atlasUpload = function (options) {
		return this.each(function () {
			var id = $(this).attr('id');
			var label = options.buttonText || 'Browse...';
			var button = $('<a href="javascript:;" class="uibutton icon add"></a>').text(label).attr('data-upload-button-for', id);
			var input = $('<input type="file" accept="image/jpeg,image/png,image/gif" style="display: none" />').attr('data-upload-for', id);
			$(this).after(input).after(button);

			button.click(function () {
				input.click();
			});

			input.change(function () {
				var file = this.files && this.files[0];
				if (!file) {
					return;
				}

				function done() {
					button.text(label).removeClass('disable');
					input.val(''); // so choosing the same file again uploads it again
				}
				function fail(code, message, text) {
					done();
					if (options.onUploadError) {
						options.onUploadError(file, code, message, text);
					}
				}

				var data = new FormData();
				data.append('Filedata', file);

				var xhr = new XMLHttpRequest();
				xhr.open('POST', options.uploader);
				xhr.upload.onprogress = function (e) {
					if (e.lengthComputable) {
						button.text('Uploading ' + Math.round(e.loaded / e.total * 100) + '%');
					}
				};
				xhr.onload = function () {
					var reply = xhr.responseText;
					if (xhr.responseURL && xhr.responseURL.indexOf('login') != -1) {
						fail(401, 'Logged out', 'You have been logged out. Please log in again and retry.');
					} else if (xhr.status != 200) {
						fail(xhr.status, xhr.statusText, 'Upload failed (error ' + xhr.status + ').');
					} else if ($.trim(reply) == '') {
						fail(413, 'No reply', 'Upload failed. The file may be too large.');
					} else {
						done();
						if (options.onUploadSuccess) {
							options.onUploadSuccess(file, reply, true);
						}
					}
				};
				xhr.onerror = function () {
					fail(0, 'Network error', 'Upload failed. Please check your connection and try again.');
				};

				button.text('Uploading...').addClass('disable');
				xhr.send(data);
			});
		});
	};
}(jQuery));
