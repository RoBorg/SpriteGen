var uploader;

YAHOO.widget.Logger.enableBrowserConsole();

YAHOO.util.Event.onDOMReady(function () { 
var uiLayer = YAHOO.util.Dom.getRegion('selectLink');
var overlay = YAHOO.util.Dom.get('uploaderOverlay');
YAHOO.util.Dom.setStyle(overlay, 'width', uiLayer.right-uiLayer.left + "px");
YAHOO.util.Dom.setStyle(overlay, 'height', uiLayer.bottom-uiLayer.top + "px");
});

// Custom URL for the uploader swf file (same folder).
YAHOO.widget.Uploader.SWFURL = "uploader.swf";

function createUploader()
{
	// Instantiate the uploader and write it to its placeholder div.
	uploader = new YAHOO.widget.Uploader( "uploaderOverlay" );

	// Add event listeners to various events on the uploader.
	// Methods on the uploader should only be called once the 
	// contentReady event has fired.

	uploader.addListener('contentReady', handleContentReady);
	uploader.addListener('fileSelect', onFileSelect)
	uploader.addListener('uploadStart', onUploadStart);
	uploader.addListener('uploadProgress', onUploadProgress);
	uploader.addListener('uploadCancel', onUploadCancel);
	//uploader.addListener('uploadComplete', onUploadComplete);
	uploader.addListener('uploadCompleteData', onUploadComplete);
	uploader.addListener('uploadError', onUploadError);
	uploader.addListener('rollOver', handleRollOver);
	uploader.addListener('rollOut', handleRollOut);
	uploader.addListener('click', handleClick);
}
	
// Variable for holding the filelist.
var fileList = {};

// When the mouse rolls over the uploader, this function
// is called in response to the rollOver event.
// It changes the appearance of the UI element below the Flash overlay.
function handleRollOver () {
}

// On rollOut event, this function is called, which changes the appearance of the
// UI element below the Flash layer back to its original state.
function handleRollOut () {
}

// When the Flash layer is clicked, the "Browse" dialog is invoked.
// The click event handler allows you to do something else if you need to.
function handleClick () {
}

// When contentReady event is fired, you can call methods on the uploader.
function handleContentReady () {
try
{
	uploader.setAllowLogging(true);
	uploader.setAllowMultipleFiles(true);
	
	var ff = new Array({description:"Images and Compressed Files", extensions:"*.jpg;*.jpeg;*.png;*.gif;*.zip"});
	uploader.setFileFilters(ff);
}
catch (err)
{
	console.log(err);
}
}

// Actually uploads the files. In this case,
// uploadAll() is used for automated queueing and upload 
// of all files on the list.
// You can manage the queue on your own and use "upload" instead,
// if you need to modify the properties of the request for each
// individual file.
function upload()
{
	if (fileList != null)
	{
		var numberOfFiles = 0;
		for (var i in fileList)
		{
			if (!fileList[i].uploaded)
				numberOfFiles++;
		}
		
		uploader.setSimUploadLimit(Math.min(4, numberOfFiles));
		
		for (var i in fileList)
		{
			if (!fileList[i].uploaded)
				uploader.upload(i, 'api.php?upload', 'POST', {token: sessionId}, 'img');
		}
	}	
}

// Fired when the user selects files in the "Browse" dialog
// and clicks "Ok".
function onFileSelect(event)
{
	if (('fileList' in event) && (event.fileList != null))
	{
		for (var i in event.fileList)
		{
			if (!fileList[i])
				fileList[i] = event.fileList[i];
		}
		
		createDataTable(fileList);
		upload();
	}
}

function createDataTable(entries) {
  rowCounter = 0;
  this.fileIdHash = {};
  this.dataArr = [];
  for(var i in entries) {
	 var entry = entries[i];
	 if (!entry.progress)
		entry.progress = "<div style='height:5px;width:100px;background-color:#CCC;'></div>";
	 dataArr.unshift(entry);
  }

  for (var j = 0; j < dataArr.length; j++) {
	this.fileIdHash[dataArr[j].id] = j;
  }

	var myColumnDefs = [
		{key:"name", label: "File Name", sortable:true},
		{key:"size", label: "Size", sortable:true},
		{key:"progress", label: "Upload progress", sortable:false},
		{key:"message", label: "Message", sortable:false}
	];

  this.myDataSource = new YAHOO.util.DataSource(dataArr);
  this.myDataSource.responseType = YAHOO.util.DataSource.TYPE_JSARRAY;
  this.myDataSource.responseSchema = {
	  fields: ["id","name","created","modified","type", "size", "progress", "message"]
  };

  this.singleSelectDataTable = new YAHOO.widget.DataTable("dataTableContainer",
		   myColumnDefs, this.myDataSource, {
			   selectionMode:"single"
		   });
}

// Do something on each file's upload start.
function onUploadStart(event) {

}

// Do something on each file's upload progress event.
function onUploadProgress(event)
{
	rowNum = fileIdHash[event.id];
	prog = Math.round(100 * (event.bytesLoaded / event.bytesTotal));
	progbar = '<div style="height: 5px; width: 100px; background-color: #CCC;" title="' + event.bytesTotal + '"><div style="height: 5px; background-color: #F00; width: ' + prog + 'px;"></div></div>';
	
	singleSelectDataTable.updateRow(rowNum, {name: dataArr[rowNum]['name'], size: dataArr[rowNum]['size'], progress: progbar});	
}

// Do something when each file's upload is complete.
function onUploadComplete(event)
{
    try
	{
		var data = YAHOO.lang.JSON.parse(event.data);
    }
	catch(err)
	{
		console.log(err);
		
		var data = {message: 'An error has occurred: ' + err.message, error: true};
	}
	
	rowNum = fileIdHash[event.id];
	
	progbar = '<div style="height: 5px; width: 100px; background-color: #CCC;" title="Done"><div style="height: 5px; background-color: #F00; width: 100px;"></div></div>';
	singleSelectDataTable.updateRow(rowNum, {name: dataArr[rowNum]['name'], size: dataArr[rowNum]['size'], progress: progbar, message:data.message});
	fileList[event.id].progress = progbar;
	fileList[event.id].message = data.message;
	fileList[event.id].uploaded = true;
	
	var attributes = { opacity: { to: 1 } };
    var anim = new YAHOO.util.Anim('settings', attributes, 0.5, YAHOO.util.Easing.easeOut); 
	anim.animate();
}

// Do something if a file upload throws an error.
// (When uploadAll() is used, the Uploader will
// attempt to continue uploading.
function onUploadError(event) {
alert('error!');
}

// Do something if an upload is cancelled.
function onUploadCancel(event) {

}




function CssSprite()
{
	this.apiUrl = 'api.php';
	this.loadingTimer = 0;
}

CssSprite.prototype.startLoading = function()
{
	this.stopError();
	
	var attributes =
	{
		opacity: { from: 0, to: 1 },
		height: { from: 0, to: 60 }
	};
	
    var anim = new YAHOO.util.Anim('loading-container', attributes, 0.5, YAHOO.util.Easing.easeOut); 
	anim.animate();
	
	var nodes = YAHOO.util.Dom.get('loading').getElementsByTagName('li');
	var nodeIndex = 0;
	
	for (var i = 0; i < nodes.length; i++)
	{
		nodes[i].style.display = i ? 'none' : 'block';
		YAHOO.util.Dom.setStyle(nodes[i], 'opacity', i ? 0 : 1);
		YAHOO.util.Dom.setStyle(nodes[i], 'width', i ? 0 : '600px');
	}
	
	var timer = this.loadingTimer = setInterval(function()
	{
		var oldNode = nodes[nodeIndex++];
		var newNode = nodes[nodeIndex];
		
		var attributes =
		{
			opacity: { from: 1, to: 0 },
			width: { from: 600, to: 0 }
		};
		
		var anim = new YAHOO.util.Anim(oldNode, attributes, 0.5, YAHOO.util.Easing.easeIn);
		anim.onComplete.subscribe(function() { var el = this.getEl(); el.style.display = 'none'; });
		anim.animate();
		
		var attributes =
		{
			opacity: { from: 0, to: 1 },
			width: { from: 0, to: 600 }
		};
		
		YAHOO.util.Dom.setStyle(newNode, 'opacity', 0);
		newNode.style.display = 'block';
		var anim = new YAHOO.util.Anim(newNode, attributes, 0.5, YAHOO.util.Easing.easeIn);
		anim.animate();
		
		if (nodeIndex == nodes.length - 1)
			clearInterval(timer);
	}, 4000);
}

CssSprite.prototype.stopLoading = function()
{
	clearInterval(this.loadingTimer);
	var attributes =
	{
		opacity: { to: 0 },
		height: { to: 0 }
	};
	
    var anim = new YAHOO.util.Anim('loading-container', attributes, 0.5, YAHOO.util.Easing.easeOut); 
	anim.animate();
}

CssSprite.prototype.startError = function(str)
{
	var elm = this.empty(YAHOO.util.Dom.get('error-message'));
	elm.appendChild(document.createTextNode(str));
	
	var attributes =
	{
		opacity: { from: 0, to: 1 },
		height: { from: 0, to: YAHOO.util.Dom.get('error').offsetHeight + 20 }
	};
	
    var anim = new YAHOO.util.Anim('error-container', attributes, 0.5, YAHOO.util.Easing.easeOut); 
	anim.animate();
}

CssSprite.prototype.stopError = function()
{
	var attributes =
	{
		opacity: { to: 0 },
		height: { to: 0 }
	};
	
    var anim = new YAHOO.util.Anim('error-container', attributes, 0.5, YAHOO.util.Easing.easeOut); 
	anim.animate();
}

CssSprite.prototype.createSprite = function()
{
	this.empty(YAHOO.util.Dom.get('dataTableContainer'));
	this.startLoading();
	
	var self = this;
	var url = this.apiUrl + '?create';
	var callback =
	{
		success: this.createSuccess,
		failure: this.createFailure,
		scope: self,
	};
	
	YAHOO.util.Connect.setForm(YAHOO.util.Dom.get('settings')); 
	var transaction = YAHOO.util.Connect.asyncRequest('GET', url, callback);
}

CssSprite.prototype.createSuccess = function(o)
{
	this.stopLoading();
	
	try
	{
		var data = YAHOO.lang.JSON.parse(o.responseText);
    }
	catch(err)
	{
		console.log(err);
		
		var data = {message:'An invalid response was received: ' + o.responseText, error:true};
	}
	
	var container = this.empty(YAHOO.util.Dom.get('resultsContainer'));
	container.style.display = 'none';
	
	if (data.error)
		return this.startError(data.message);
	
	container.style.width = data.width + 'px';
	container.style.height = data.height + 'px';
	container.style.display = 'block';
	
	// Add the download link
	var elm = YAHOO.util.Dom.get('downloadLink');
	elm.href = data.url + '?rnd=' + Math.random();
	elm.style.display = 'block';
	var attributes = { opacity: { from: 0, to: 1 } };
	var anim = new YAHOO.util.Anim('downloadLink', attributes, 0.5, YAHOO.util.Easing.easeOut);
	anim.animate();
	
	// Create the "imagemap"
	var map = document.createElement('div');
	container.appendChild(map);
	map.style.position = 'absolute';
	map.style.width = data.width + 'px';
	map.style.height = data.height + 'px';
	map.style.backgroundImage = 'url(' + data.url + '?rnd=' + Math.random() + ')';
	
	for (var i = 0; i < data.info.length; i++)
	{
		var e = document.createElement('div');
		map.appendChild(e);
		
		e.style.left = data.info[i].x + 'px';
		e.style.top = data.info[i].y + 'px';
		e.style.width = data.info[i].width + 'px';
		e.style.height = data.info[i].height + 'px';
		
		var title = document.createElement('span');
		title.appendChild(document.createTextNode(data.info[i].file));
		e.appendChild(title);
	}
	
	var attributes = { height: { from: 0, to: data.height }, opacity: { from: 0, to: 0 } };
	
    var anim = new YAHOO.util.Anim('resultsContainer', attributes, 0.5, YAHOO.util.Easing.easeOut);
	anim.onComplete.subscribe(function()
	{
		//container.style.visibility = 'visible';
		var attributes = { opacity: { from: 0, to: 1 } };
		var anim = new YAHOO.util.Anim('resultsContainer', attributes, 0.25, YAHOO.util.Easing.easeOut);
		anim.animate();
	});
	anim.animate();
	
	// Show the css
	var cssContainer = this.empty(YAHOO.util.Dom.get('cssContainer'));
	var pre = document.createElement('pre');
	cssContainer.appendChild(pre);
	pre.className = 'brush: css';
	pre.appendChild(document.createTextNode(data.css));
	
	SyntaxHighlighter.highlight({gutter:false,collapse:true}, pre);
	
	// Size and download estimates
	var elm = this.empty(YAHOO.util.Dom.get('size-before'));
	elm.appendChild(document.createTextNode(this.formatMemory(data.oldSize)));
	
	var elm = this.empty(YAHOO.util.Dom.get('size-after'));
	elm.appendChild(document.createTextNode(this.formatMemory(data.newSize)));
	
	// Assume 500 byte header, 2 simultaneous downloads (very simple estimate) and 64 kbps and 100ms latency
	var oldDownload = Math.round(((data.oldSize + (500 * data.info.length) / 2) / (32 * 1024)) + (.100 * data.info.length));
	var elm = this.empty(YAHOO.util.Dom.get('download-before'));
	elm.appendChild(document.createTextNode(oldDownload));
	
	// Only one header & download for the sprite
	var newDownload = Math.round((data.newSize + 500) / (64 * 1024));
	var elm = this.empty(YAHOO.util.Dom.get('download-after'));
	elm.appendChild(document.createTextNode(newDownload));
	
	YAHOO.util.Dom.get('sizes').style.display = 'block';
	var attributes = { height: { from: 0, to: 70 }, opacity: { from: 0, to: 1 } };
	var anim = new YAHOO.util.Anim('sizes', attributes, 0.5, YAHOO.util.Easing.easeOut);
	anim.animate();
	
	this.stopLoading();
}

CssSprite.prototype.createFailure = function(o)
{
	alert(this.apiUrl + ': fail')
}

CssSprite.prototype.empty = function(elm)
{
	while (elm.firstChild)
		elm.removeChild(elm.firstChild);
	
	return elm;
}


CssSprite.prototype.formatMemory = function(mem)
{
	var units = ['b', 'Kb', 'Mb', 'Gb'];
	var unit = 0;
	
	while (mem > 1024 && units[unit + 1])
	{
		mem /= 1024;
		unit++;
	}
	
	return mem.toFixed(unit) + ' ' + units[unit];
}

var s = new CssSprite();





function showAdvanced()
{
    var attributes =
	{
		height: { from: 0, to: 240 },
		opacity: { from: 0, to: 1 }
    };
	
    var anim = new YAHOO.util.Anim('advanced', attributes, 0.5, YAHOO.util.Easing.easeOut);
	anim.animate();
    
	var attributes =
	{
		opacity: { from: 1, to: 0 }
    };
	
    var anim = new YAHOO.util.Anim('advanced-handle', attributes, 0.5, YAHOO.util.Easing.easeOut); 
	anim.onComplete.subscribe(function() { var el = this.getEl(); el.parentNode.removeChild(el); });
	anim.animate();
	
	return false;
}

function showJpegSettings(s)
{
	var on = (s.options[s.selectedIndex].value == 'jpeg');
	
	var attributes = { opacity: { to: on ? 1 : 0.1 } };
	
    var anim = new YAHOO.util.Anim('jpeg-settings', attributes, 0.5, YAHOO.util.Easing.easeOut); 
	anim.animate();
}