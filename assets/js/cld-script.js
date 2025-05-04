// Placeholder for Custom Location Delivery frontend JS
// Will handle popup/modal, location selection, and Google Maps API integration 

jQuery(function($){
  // Only run if the detect button exists
  if ($('#cld-detect-location-btn').length) {
    $('#cld-detect-location-btn').off('click').on('click', function(){
      if (!navigator.geolocation) {
        alert('Geolocation is not supported by your browser.');
        return;
      }
      $('#cld-detect-location-btn').text('Detecting...');
      navigator.geolocation.getCurrentPosition(function(position) {
        var lat = position.coords.latitude;
        var lng = position.coords.longitude;
        var apiKey = cld_maps_api_key || '';
        if (!apiKey) {
          alert('Google Maps API key is not set.');
          $('#cld-detect-location-btn').text('Detect My Location');
          return;
        }
        var url = 'https://maps.googleapis.com/maps/api/geocode/json?latlng='+lat+','+lng+'&key='+apiKey;
        $.get(url, function(resp){
          if (resp.status === 'OK') {
            var pincode = null;
            var results = resp.results;
            for (var i=0; i<results.length; i++) {
              var comps = results[i].address_components;
              for (var j=0; j<comps.length; j++) {
                if (comps[j].types.indexOf('postal_code') !== -1) {
                  pincode = comps[j].long_name;
                  break;
                }
              }
              if (pincode) break;
            }
            if (pincode) {
              var found = false;
              $('.cld-location-option').each(function(){
                if ($(this).data('pincode') == pincode) {
                  $(this).click();
                  found = true;
                  return false;
                }
              });
              if (!found) alert('Sorry, we do not deliver to your detected pincode ('+pincode+').');
            } else {
              alert('Could not detect your pincode.');
            }
          } else {
            alert('Could not get address from Google Maps.');
          }
          $('#cld-detect-location-btn').text('Detect My Location');
        });
      }, function(){
        alert('Unable to retrieve your location.');
        $('#cld-detect-location-btn').text('Detect My Location');
      });
    });
  }
}); 