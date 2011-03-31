//todo refactor to css-sprites.js

		YAHOO.util.Dom.setStyle('settings', 'opacity', .1);
		YAHOO.util.Dom.get('output_type').onchange();

		(function() {
			var Event = YAHOO.util.Event,
				Dom   = YAHOO.util.Dom,
				lang  = YAHOO.lang,
				slider, 
				bg="slider-bg", thumb="slider-thumb", 
				valuearea="slider-value", textfield="jpeg_quality"

			// The slider can move 0 pixels up
			var topConstraint = 0;

			// The slider can move x pixels down
			var bottomConstraint = 200;

			// Custom scale factor for converting the pixel offset into a real value
			var scaleFactor = .5;

			// The amount the slider moves when the value is changed with the arrow
			// keys
			var keyIncrement = 10;

			var tickSize = 10;

			Event.onDOMReady(function() {

				slider = YAHOO.widget.Slider.getHorizSlider(bg, 
								 thumb, topConstraint, bottomConstraint, 10);

				slider.animate = true;
				slider.setValue(150, false);

				slider.getRealValue = function() {
					return Math.round(this.getValue() * scaleFactor);
				}

				slider.subscribe("change", function(offsetFromStart) {

					var valnode = Dom.get(valuearea);
					var fld = Dom.get(textfield);
					
					// update the text box with the actual value
					var actualValue = slider.getRealValue();
					fld.value = actualValue;
					valnode.innerHTML = actualValue;

					// Update the title attribute on the background.  This helps assistive
					// technology to communicate the state change
					Dom.get(bg).title = "slider value = " + actualValue;
				});

				// Use setValue to reset the value to white:
				Event.on("putval", "click", function(e) {
					slider.setValue(100, false); //false here means to animate if possible
				});
				
				// Use the "get" method to get the current offset from the slider's start
				// position in pixels.  By applying the scale factor, we can translate this
				// into a "real value
				Event.on("getval", "click", function(e) {
					YAHOO.log("Current value: "   + slider.getValue() + "\n" + 
							  "Converted value: " + slider.getRealValue(), "info", "example"); 
				});
			});
		})();