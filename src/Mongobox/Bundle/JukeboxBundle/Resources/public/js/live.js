var livePlayer;
var connection;

LivePlayer = function()
{
	this.initialize = function(currentPlaylistId, currentUserId)
	{
        this.playlistId = currentPlaylistId;
        this.userId     = currentUserId;

		this.getPlaylistScores(currentPlaylistId);
        this.synchronizePlayerVolume();

		this.initializeVideoRating();
        this.initializeVolumeControl();

        $("#putsch-button").click(function(event) {
            event.preventDefault();
            this.sendPutschAttempt();
        }.bind(this));
	},

    this.initializeVideoRating = function()
    {
        $('#up-vote').unbind('click');
        $('#up-vote').click(function(event) {
            event.preventDefault();

            $.post(voteUrl, {
                playlist: this.playlistId,
                vote: 'up',
                current: 1
            }, function(response) {
                this.playlistScoresUpdate(response);
            }.bind(this));
        }.bind(this));

        $('#down-vote').unbind('click');
        $('#down-vote').click(function(event) {
            event.preventDefault();

            $.post(voteUrl, {
                playlist: this.playlistId,
                vote: 'down',
                current: 1
            }, function(response) {
                this.playlistScoresUpdate(response);
            }.bind(this));
        }.bind(this));
    },

    this.initializeVolumeControl = function()
    {
        $('#up-volume').unbind('click');
        $('#up-volume').click(function(event) {
            event.preventDefault();
            this.updatePlayerVolume('up');
        }.bind(this));

        $('#down-volume').unbind('click');
        $('#down-volume').click(function(event) {
            event.preventDefault();
            this.updatePlayerVolume('down');
        }.bind(this));
    },

	this.synchronizePlayerState = function(params)
	{
        switch (params.action) {
            case 'update_scores':
                var scores = JSON.parse(params.scores);
                this.updatePlaylistScores(scores);

                return;
            break;

            case 'update_volume':
                this.synchronizePlayerVolume();

                return;
            break;

            case 'refresh_page':
                if (parseInt(params.userId) === parseInt(this.userId)) {
                    window.location.reload();

                    return;
                }
            break;

            case 'putsch_acknowledgment':
                clearInterval(this.putschTimer);

                $('#putsch-modal').modal('show');
                $('.loader').show();
                $('#putsch-modal .modal-content').html($('#putsch-request-callback').html());
                $('.loader').hide();

                return;
            break;

            case 'refuse_putsch':
                if (parseInt(params.userId) === parseInt(this.userId)) {
                    $('#putsch-modal').modal('show');
                    $('.loader').show();
                    $('#putsch-modal .modal-content').html($('#putsch-refuse-callback').html());
                    $('.loader').hide();

                    return;
                }
            break;
        }

        switch(params.status) {
            case 1:
                player.seekTo(params.currentTime);
                player.playVideo();

            break;

            case 2:
                player.seekTo(params.currentTime);
                player.pauseVideo();

            break;

            case 0:
                player.loadVideoById({
                    videoId: params.videoId,
                    volume: params.videoVolume
                });

                this.synchronizePlayerVolume();
                this.initialize(params.playlistId);
            break;
        }
	},

	this.sendParameters = function(params)
	{
		var json = JSON.stringify(params);
		connection.send(json);
	},

	this.getPlaylistScores = function(playlistId)
	{
		$.get(scoreUrl, {
			playlist: this.playlistId
        }, function(response) {
			var scores = JSON.parse(response);
			this.updatePlaylistScores(scores);
		}.bind(this));
	},

	this.updatePlaylistScores = function(scores)
	{
		$('#up-score').text('(' + scores.upVotes + ')');
		$('#down-score').text('(' + scores.downVotes + ')');
		$('#video-score').text('Score : ' + scores.votesRatio);
	},

	this.playlistScoresUpdate = function(data)
	{
		var scores = JSON.parse(data);
		this.updatePlaylistScores(scores);

		var params = new Object();
		params.action	= 'update_scores';
		params.scores	= data;

		this.sendParameters(params);
	},

    this.updatePlayerVolume = function(direction)
    {
        $.ajax({
            type: 'POST',
            dataType: 'json',
            url: volumeUrl,
            data: {
                'playlist' : this.playlistId,
                'vote': direction
            }
        }).done(function(data) {
            this.updateVolumeControl(data);

            var params = new Object();
            params.action	= 'update_volume';
            params.volume   = data;

            this.sendParameters(params);
        }.bind(this));
    },

    this.synchronizePlayerVolume = function()
    {
        $.ajax({
            type: 'GET',
            dataType: 'json',
            url: volumeUrl,
            data: {
                'playlist' : this.playlistId
            }
        }).done(function(data) {
            this.updateVolumeControl(data);
        }.bind(this));
    },

    this.updateVolumeControl = function(data)
    {
        if (typeof player !== 'undefined' && typeof player.setVolume === 'function') {
            player.setVolume(data.currentVolume);
        }

        $('#volume-up-votes').text('(' + data.upVotes + ')');
        $('#volume-down-votes').text('(' + data.downVotes + ')');
        $('#video-volume').text('Volume : ' + data.currentVolume + '%');
    },

    this.sendPutschAttempt = function ()
    {
        $.ajax({
            type: 'POST',
            dataType: 'json',
            url: putschEligibilityUrl,
            data: {
                'user' : this.userId
            }
        }).done(function(data) {
            if (data.result === 'allow') {
                var params = new Object();
                params.action   = 'putsch_attempt';
                params.userId   = this.userId;

                this.sendParameters(params);
                this.waitPutschAcknowledgment();
            } else {
                $('#putsch-modal').modal('show');
                $('.loader').show();
                $('#putsch-modal .modal-content').html(data.details);
                $('.loader').hide();
            }
        }.bind(this));
    },

    this.waitPutschAcknowledgment = function()
    {
        var maximumWaiting  = 5;
        var currentWaiting  = 0;

        this.putschTimer = setInterval(function() {
            currentWaiting++;
            if (currentWaiting === maximumWaiting) {
                clearInterval(this.putschTimer);

                $.ajax({
                    type: 'POST',
                    dataType: 'json',
                    url: adminSwitchUrl,
                    data: {
                        'user' : this.userId
                    }
                }).done(function(data) {
                    if (data.status === 'done') {
                        window.location.reload();
                    }
                }.bind(this));
            }
        }.bind(this), 1000);
    }
};
