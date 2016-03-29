document.addEventListener('DOMContentLoaded', function() {
    var     apiKeyInput             = document.getElementById('api-key'),
        clientIdInput           = document.getElementById('api-client-id'),
        clientSecretInput       = document.getElementById('api-client-secret'),
        accessToken             = null,
        views                   = document.getElementsByClassName('view'),
        tournamentListContainer = document.getElementById('tournament-list-container'),
        tournamentList          = tournamentListContainer.getElementsByTagName('tbody')[0],
        matchListContainer      = document.getElementById('match-list-container'),
        matchList               = matchListContainer.getElementsByTagName('tbody')[0];

    moment.locale('fr');
    apiKeyInput.value = getParameterByName('api_key');
    clientIdInput.value = getParameterByName('client_id');
    clientSecretInput.value = getParameterByName('client_secret');

    var onTournamentImportSuccess = function(data) {
        for(i=0; i<views.length; i++) {
            views[i].className = 'view hide';
        }
        tournamentList.innerHTML = '';

        for(i=0; i<data.length; i++) {
            id = data[i].id;
            name = data[i].name;
            discipline = data[i].discipline;
            size = data[i].size;
            isPublic = data[i].public?'public':'draft';

            row =   '<tr>' +
                        '<td>'+name+'</td>' +
                        '<td>'+discipline+'</td>' +
                        '<td>'+size+'</td>' +
                        '<td>'+isPublic+'</td>' +
                        '<td>' +
                            '<button data-id="'+id+'" class="button-import-matches pure-button pure-button-primary">Edit</button>' +
                        '</td>' +
                    '</tr>'
            ;
            tournamentList.innerHTML += row;
        }

        var importMatchButtons = tournamentList.getElementsByClassName('button-import-matches');
        for (var i=0; i<importMatchButtons.length; i++){
            importMatchButtons[i].addEventListener('click', importMatchesAction);
        }
        tournamentListContainer.className = 'view';
    };

    var onMatchImportSuccess = function (data) {
        for(i=0; i<views.length; i++) {
            views[i].className = 'view hide';
        }
        matchList.innerHTML = '';

        for(i=0; i<data.length; i++) {
            match_id = data[i].id;
            tournament_id = data[i].tournament_id;
            stage_number = data[i].stage_number;
            group_number = data[i].group_number;
            round_number = data[i].round_number;
            opponent1_name = data[i].opponents[0].participant !== null? data[i].opponents[0].participant.name: '';
            opponent2_name = data[i].opponents[1].participant !== null? data[i].opponents[1].participant.name: '';

            date = '';
            time = '';
            interpretedDate = moment(data[i].date, 'YYYY-MM-DDTHH:mm:ssZZ');
            if (interpretedDate.isValid()){
                date = interpretedDate.format('L');
                time = interpretedDate.format('LT');
            }

            row =   '<tr>' +
                        '<td>' +
                            '<div class="grid-flex vertical">' +
                                '<span style="font-size: 12px;">Stage ' + stage_number + ' / Group ' + group_number + ' / Round ' + round_number + '</span>' +
                                '<br />' +
                                '<strong>' + opponent1_name + '</strong><br/>' + ' vs ' + '<br/><strong>' + opponent2_name + '</strong>' +
                            '</div>' +
                        '</td>' +
                        '<td><div class="form-row"><div class="form-field"><input type="text" class="date" value="' + date + '" placeholder="JJ/MM/AAAA" /></div></div></td>' +
                        '<td><div class="form-row"><div class="form-field"><input type="text" class="time" value="' + time + '" placeholder="HH:MM" /></div></div></td>' +
                        '<td>' +
                            '<button data-match="' + match_id + '" data-tournament="' + tournament_id + '" class="button-save-match pure-button pure-button-primary">Save</button>' +
                        '</td>' +
                    '</tr>'
            ;
            matchList.innerHTML += row;
        }


        var saveMatchButtons = matchList.getElementsByClassName('button-save-match');
        for (var i=0; i<saveMatchButtons.length; i++){
            saveMatchButtons[i].addEventListener('click', saveMatchAction);
        }
        matchListContainer.className = 'view';
    };

    var onMatchSaveSuccess = function (data) {
        console.log('save matches success', data);
    };

    var onTournamentImportFailure = function (status, data) {
        if (401 === status) {
            getAccessToken(apiKeyInput.value, clientIdInput.value, clientSecretInput.value, onGetAccessSuccess, null);
        }
    };

    var onMatchImportFailure = function (status, data) {
        if (401 === status) {
            getAccessToken(apiKeyInput.value, clientIdInput.value, clientSecretInput.value, onGetAccessSuccess, null);
        }
    };

    var onMatchSaveFailure = function (status, data) {
        if (401 === status) {
            getAccessToken(apiKeyInput.value, clientIdInput.value, clientSecretInput.value, onGetAccessSuccess, null);
        } else {
            alert(data);
        }
    };

    var onGetAccessSuccess = function(data) {
        accessToken = data.access_token;
        importTournamentList(apiKeyInput.value, accessToken, onTournamentImportSuccess, null)
    };

    var importTournamentList = function(apiKey, accessToken, successHandler, errorHandler) {
        var r = new XMLHttpRequest();
        r.open('GET', 'https://api.toornament.com/v1/me/tournaments', true);
        r.setRequestHeader('Content-Type', 'application/json');
        r.setRequestHeader('X-Api-Key', apiKey);
        r.setRequestHeader('Authorization', 'Bearer ' + accessToken);
        r.onreadystatechange = function () {
            if (r.readyState !== 4) {
                return;
            }
            if (successHandler !== null && r.status == 200) {
                successHandler(JSON.parse(r.responseText))
            } else if (errorHandler !== null) {
                errorHandler(r.status, r.responseText)
            }
        };
        r.send();
    };

    var importMatchList = function(tournamentId, apiKey, accessToken, successHandler, errorHandler) {
        var r = new XMLHttpRequest();
        r.open('GET', 'https://api.toornament.com/v1/tournaments/' + tournamentId + '/matches', true);
        r.setRequestHeader('Content-Type', 'application/json');
        r.setRequestHeader('X-Api-Key', apiKey);
        r.setRequestHeader('Authorization', 'Bearer ' + accessToken);
        r.onreadystatechange = function () {
            if (r.readyState !== 4) {
                return;
            }
            if (successHandler !== null && r.status == 200) {
                successHandler(JSON.parse(r.responseText))
            } else if (errorHandler !== null) {
                errorHandler(r.status, r.responseText)
            }
        };
        r.send();
    };

    var saveMatch = function(tournamentId, matchId, matchDate, matchTimezone, apiKey, accessToken, successHandler, errorHandler) {
        var r = new XMLHttpRequest();
        r.open('PATCH', 'https://api.toornament.com/v1/tournaments/' + tournamentId + '/matches/' + matchId, true);
        r.setRequestHeader('Content-Type', 'application/json');
        r.setRequestHeader('X-Api-Key', apiKey);
        r.setRequestHeader('Authorization', 'Bearer ' + accessToken);
        r.onreadystatechange = function () {
            if (r.readyState !== 4) {
                return;
            }
            if (successHandler !== null && r.status == 200) {
                successHandler(JSON.parse(r.responseText))
            } else if (errorHandler !== null) {
                errorHandler(r.status, r.responseText)
            }
        };
        r.send(JSON.stringify({
            date:      matchDate,
            timezone:  matchTimezone
        }));
    };

    var getAccessToken = function(apiKey, clientId, clientSecret, successHandler, errorHandler) {
        var r = new XMLHttpRequest();
        r.open('GET', 'https://api.toornament.com/oauth/v2/token?grant_type=client_credentials&client_id=' + clientId + '&client_secret=' + clientSecret, true);
        r.setRequestHeader('Content-Type', 'application/json');
        r.setRequestHeader('X-Api-Key', apiKey);
        r.onreadystatechange = function () {
            if (r.readyState !== 4) {
                return;
            }
            if (successHandler !== null && r.status == 200) {
                successHandler(JSON.parse(r.responseText))
            } else if (errorHandler !== null) {
                errorHandler(r.status, r.responseText)
            }
        };
        r.send();
    };

    var importTournamentsAction = function(e) {
        e.preventDefault();
        importTournamentList(apiKeyInput.value, accessToken, onTournamentImportSuccess, onTournamentImportFailure);
    };

    var importMatchesAction = function(e){
        e.preventDefault();
        importMatchList(e.target.dataset.id, apiKeyInput.value, accessToken, onMatchImportSuccess, onMatchImportFailure);
    };

    var saveMatchAction = function(e) {
        e.preventDefault();
        date = e.target.parentNode.parentNode.getElementsByClassName("date")[0].value;
        time = e.target.parentNode.parentNode.getElementsByClassName("time")[0].value;
        saveMatch(
            e.target.dataset.tournament,
            e.target.dataset.match,
            moment(date+' '+time, 'L LT').format('YYYY-MM-DDTHH:mm:ssZZ'),
            document.getElementById('timezone').value,
            apiKeyInput.value,
            accessToken,
            onMatchSaveSuccess,
            onMatchSaveFailure
        );
    };

    document.getElementById('button-import-tournament').addEventListener('click', importTournamentsAction);
}, false );

function getParameterByName(name) {
    var url = window.location.href;
    name = name.replace(/[\[\]]/g, "\\$&");
    var regex = new RegExp("[?&]" + name + "(=([^&#]*)|&|#|$)"),
        results = regex.exec(url);
    if (!results) return null;
    if (!results[2]) return '';
    return decodeURIComponent(results[2].replace(/\+/g, " "));
}
