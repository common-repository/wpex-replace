/*<![CDATA[*/
jQuery(function($)
{
	checkUrl = function(url)
	{
    //if (the_ip == '::1' || the_id == '127.0.0.1' || the_ip == 'localhost');
    //  return true;

		return /^https?:\/\/(www\.)?([a-z0-9_\-\.]{2,}\.)+[a-z]{2,5}/.test(url);
	};

  $(document).ready(function()
  {
		$('#btn-search-urls').click(function()
		{
			$('#wpex-replace-string-tools .loader img').show(0, function()
			{
				$.delay(100);
			});
		});
		
		// handle the doaction buttons
		$('.list-search-urls #doaction, .list-search-urls #doaction2').click(function()
		{
			var bulkaction = $(this).parents('.bulkactions');
			var action = bulkaction.find('select[name*=action]').val();
			if (action != 'replace')
				return true;

			var count = 0;
			var error = '';
			var urls = $(".list-search-urls input[name='url[]']:checked");
			urls.each(function()
			{
				var row = $(this).parents('tr');
				var oldText = $(this).val();
				var newText = row.find("input[name='replace[]']");

				var oldValue = oldText.trim();
        if (oldValue == '')
        {
          error += ' - ' + wpxe_replace_i18n.empty_url.replace(/{which}/, 'Old').replace(/{url}/, oldValue) + "\n";
          return true;
        }

        var newValue = newText.val().trim();
				if (newValue == '')
				{
					error += ' - ' + wpxe_replace_i18n.empty_url.replace(/{which}/, 'New').replace(/{url}/, oldValue) + "\n";
					return true;
				}

        // remove trailing slash
        if (oldValue[oldValue.length-1] == '/')
          oldValue = oldValue.substr(0, oldValue.length-1);
				if (newValue[newValue.length-1] == '/')
					newValue = newValue.substr(0, newValue.length-1); 
						
				// check it's a valid url
        if (!checkUrl(oldValue))
        {
          error += ' - ' + wpxe_replace_i18n.invalid_url.replace(/{url}/, oldValue) + "\n";
          return true;
        }
				if (!checkUrl(newValue))
				{
					error += ' - ' + wpxe_replace_i18n.invalid_url.replace(/{url}/, newValue) + "\n";
					return true;
				}
			
				// check if the url is the same
				if (oldValue.toLowerCase() == newValue.toLowerCase())
				{
					error += ' - ' + wpxe_replace_i18n.same_url.replace(/{url}/, newValue) + "\n";
					return true;
				}
					
				count++;
				return true;
			});
				
			if (error.length > 0)
			{
				alert(wpxe_replace_i18n.error_occured + "\n" + error);
				return false;
			}

			if (count == 0)
			{
				alert(wpxe_replace_i18n.choose_url);
				return false;
			}
			
			return confirm(wpxe_replace_i18n.really_replace);
		});
  });	
});
/*]]>*/ 