angular.module('hrmApp.controllers', []).
    controller('settingsController', function($scope) {
        $scope.selectedInd = -1;
        $scope.settings = [
            {
                name : "Oli awesome setting 1",
                channels: 2,
                microscope: "single point confocal",
                na: 1.4,
                objectivetype: "oil",
                samplemedium: "water / buffer",
                excitation: [488, 510],
                emission: [555, 580],
                pixelsize: [100,340],
                timeinterval: 0,
                psf: "theoretical",
                aberrationcorrection: "yes",
                performaberrationcorrection: "no"


            },
            {
                name : "Oli awesome ",
                channels: 2,
                microscope: "single point confocal",
                na: 1.4,
                objectivetype: "oil",
                samplemedium: "water / buffer",
                excitation: [488, 510],
                emission: [555, 580],
                pixelsize: [100,340],
                timeinterval: 0,
                psf: "theoretical",
                aberrationcorrection: "yes",
                performaberrationcorrection: "no"


            },
            {
                name : "Oli 1",
                channels: 2,
                microscope: "single point confocal",
                na: 1.4,
                objectivetype: "oil",
                samplemedium: "water / buffer",
                excitation: [488, 510],
                emission: [555, 580],
                pixelsize: [100,340],
                timeinterval: 0,
                psf: "theoretical",
                aberrationcorrection: "yes",
                performaberrationcorrection: "no"




            }];
    });
