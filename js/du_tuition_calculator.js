(function ($, Drupal, drupalSettings) {
  Drupal.behaviors.TuitionCalculatorBehavior = {
    attach: function (context, settings) {
      // Set up Calculator values.
      var dutcSettings = {
        programs: drupalSettings.du_tuition_calculator.tc_variables.programs,
        currentAcademicYear: drupalSettings.du_tuition_calculator.tc_variables.currentAcademicYear,
        nextAcademicYear: drupalSettings.du_tuition_calculator.tc_variables.nextAcademicYear,
        flatRateLink: drupalSettings.du_tuition_calculator.tc_variables.flatRateLink
      };

      var currentStudent = false,
        selectedProgramData = null;

      var calculateTuitionCost = function() {
        var credits = 0,
          semester = $('#edit-semester').val(),
          cost = 0,
          perCredit = 0,
          aYear = dutcSettings.currentAcademicYear;

        if ($('#edit-academic-year').chosen().val() == 'next') {
          aYear = dutcSettings.nextAcademicYear;
        }

        if (selectedProgramData == null) {
          $('.dutc-cost').hide();
          $('.dutc-no-cost').show();
          $('.dutc-ayear').html(aYear);
          return;
        }

        if (currentStudent == false && selectedProgramData.yearSwitch == true) {
          $('.dutc-ayear-disclaimer').show();
        }
        else {
          $('.dutc-ayear-disclaimer').hide();
        }
        if (selectedProgramData.details.billed_per_term == true) {
          cost = selectedProgramData.details.amount_per_term * 3;
          perCredit = selectedProgramData.details.amount_per_term;
          $('.dutc-per-credit-term').html('Term');
          $('.dutc-disclaimer-term').show();
          $('.dutc-disclaimer-credit').hide();
        }
        else {
          credits = parseFloat($('#edit-credits').chosen().val());
          cost = selectedProgramData.details.per_credit * credits;
          perCredit = selectedProgramData.details.per_credit;
          $('.dutc-per-credit-term').html('Credit');
          $('.dutc-disclaimer-term').hide();
          $('.dutc-disclaimer-credit').show();
        }

        var costParts = cost.toString().split(".");
        costParts[0] = costParts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ',');
        var creditParts = perCredit.toString().split(".");
        creditParts[0] = creditParts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ',');
        $('.dutc-per-credit-cost').html('$' + creditParts.join('.'));
        $('.dutc-annual-cost').html('$' + costParts.join('.'));
        $('.dutc-ayear').html(aYear);
        $('.dutc-selected-credits').html(credits);
        $('.dutc-cost').show();
        $('.dutc-no-cost').hide();
      }

      var searchTypeToggle = function(searchType) {
        $('#edit-college').val(null).trigger('chosen:updated');
        $('#edit-degree').val(null).trigger('chosen:updated');
        $('#edit-start-year').val(null).trigger('chosen:updated');
        $('#edit-semester').val(null).trigger('chosen:updated');
        $('#edit-credits').val(null).trigger('chosen:updated');
        $('#edit-academic-year').val(null).trigger('chosen:updated');

        if (searchType == 'college') {
          $('.dutc-college').show();
          $('.dutc-degree').hide();
        }
        else {
          $('.dutc-college').hide();
          $('.dutc-degree').show();
        }

        $('.dutc-year').hide();
        $('.dutc-semester').hide();
        $('.dutc-credits').hide();
        $('.dutc-academic-year').hide();
        $('.dutc-cost').hide();
        $('.dutc-no-cost').hide();
      }

      var enrolledToggle = function() {
        $('#edit-college').val(null).trigger('chosen:updated');
        $('#edit-degree').val(null).trigger('chosen:updated');
        $('#edit-start-year').val(null).trigger('chosen:updated');
        $('#edit-semester').val(null).trigger('chosen:updated');
        $('#edit-credits').val(null).trigger('chosen:updated');
        $('#edit-academic-year').val(null).trigger('chosen:updated');

        $('.dutc-search-option').show();

        if ($('#edit-search-option-college').is(':checked')) {
          $('.dutc-college').show();
          $('.dutc-degree').hide();
        }
        else {
          $('.dutc-college').hide();
          $('.dutc-degree').show();
        }

        $('.dutc-year').hide();
        $('.dutc-semester').hide();
        $('.dutc-credits').hide();
        $('.dutc-academic-year').hide();
        $('.dutc-cost').hide();
        $('.dutc-no-cost').hide();
      }

      var findProgramData = function(programId, academicYear, semester, year) {
        var program = dutcSettings.programs[programId],
          aYear = academicYear,
          programData = {
            yearSwitch: false,
            details: null,
            yearUsed: academicYear
          };

        // Switch to current academic year if there is no data for next.
        if (aYear == dutcSettings.nextAcademicYear && aYear in program === false) {
          aYear = dutcSettings.currentAcademicYear;
          programData.yearSwitch = true;
          programData.yearUsed = aYear;
        }

        // Check for current academic year data.
        if (aYear == dutcSettings.currentAcademicYear && aYear in program === false) {
          return null;
        }

        // Find the correct semester data.
        var startYear = parseFloat(program[aYear]['years']['first']),
          thisAcademicYear = parseFloat(program[aYear]['years']['second']);
        if (year != null) {
          startYear = year;
        }
        else if (semester < 70) {
          startYear++;
        }
        var i = startYear,
          thisSemester = semester;
        while (i <= thisAcademicYear && programData.details == null) {
          while (thisSemester <= 70 && programData.details == null) {
            if (i < thisAcademicYear || thisSemester < 70) {
              if (i.toString() + thisSemester.toString() in program[aYear]) {
                programData.details = program[aYear][i.toString() + thisSemester.toString()];
                break;
              }
            }
            thisSemester += 20;
          }
          thisSemester = 10;
          i++;
        }
        if (programData.details == null) {
          return null;
        }

        programData['program'] = program['program'];
        programData['program_code'] = program['program_code'];
        programData['college'] = program['college'];
        programData['college_code'] = program['college_code'];

        return programData;
      }

      $('#edit-currently-enrolled').change(function() {
        var current = $(this).chosen().val();
        if (current == 'current') {
          currentStudent = true;
          enrolledToggle();
        }
        else if (current == 'new') {
          currentStudent = false;
          enrolledToggle();
        }
        else {
          $('.dutc-college').hide();
          $('.dutc-degree').hide();
          $('.dutc-year').hide();
          $('.dutc-semester').hide();
          $('.dutc-credits').hide();
          $('.dutc-academic-year').hide();
          $('.dutc-cost').hide();
          $('.dutc-no-cost').hide();
        }
      });

      $('#edit-search-option-degree').click(function() {
        if ($(this).is(':checked')) {
          searchTypeToggle('degree');
          $('#edit-degree option').remove();
          $('#edit-degree').append($('<option/>', {
            value: null,
            text: ''
          }));
          $.each(dutcSettings.programs, function(i, program) {
            $('#edit-degree').append($('<option/>', {
              value: i,
              text: program['program'] + ' (' + program['program_code'] + ')'
            }));
          });
          $('#edit-degree').val(null).trigger('chosen:updated');
        }
        else {
          searchTypeToggle('college');
        }
      });

      $('#edit-search-option-college').click(function() {
        if ($(this).is(':checked')) {
          searchTypeToggle('college');
        }
        else {
          searchTypeToggle('degree');
        }
      });

      $('#edit-college').change(function() {
        var collegeCode = $(this).chosen().val();
        $('#edit-degree').val(null).trigger('chosen:updated');
        $('#edit-start-year').val(null).trigger('chosen:updated');
        $('#edit-semester').val(null).trigger('chosen:updated');
        $('#edit-credits').val(null).trigger('chosen:updated');
        $('#edit-academic-year').val(null).trigger('chosen:updated');

        if (collegeCode.length > 0) {
          $('#edit-degree option').remove();
          $('#edit-degree').append($('<option/>', {
            value: null,
            text: ''
          }));
          $.each(dutcSettings.programs, function(i, program) {
            if (program['college_code'] == collegeCode) {
              $('#edit-degree').append($('<option/>', {
                value: i,
                text: program['program'] + ' (' + program['program_code'] + ')'
              }));
            }
          });
          $('#edit-degree').val(null).trigger('chosen:updated');
          $('.dutc-degree').show();
        }
        else {
          $('.dutc-degree').hide();
        }
        
        $('.dutc-year').hide();
        $('.dutc-semester').hide();
        $('.dutc-credits').hide();
        $('.dutc-academic-year').hide();
        $('.dutc-cost').hide();
        $('.dutc-no-cost').hide();
      });

      $('#edit-degree').change(function() {
        var selectValue = $(this).chosen().val();
        $('#edit-start-year').val(null).trigger('chosen:updated');
        $('#edit-semester').val(null).trigger('chosen:updated');
        $('#edit-credits').val(null).trigger('chosen:updated');
        $('#edit-academic-year').val(null).trigger('chosen:updated');

        if (selectValue.length > 0) {
          if (currentStudent) {
            $('.dutc-semester label').html('Which quarter or semester did you enter the program?');
          }
          else {
            $('.dutc-semester label').html('Which quarter or semester will you enter the program?');
          }
          $('.dutc-semester').show();
        }
        else {
          $('.dutc-semester').hide();
        }
        $('.dutc-year').hide();
        $('.dutc-credits').hide();
        $('.dutc-academic-year').hide();
        $('.dutc-cost').hide();
        $('.dutc-no-cost').hide();
      });

      $('#edit-semester').change(function() {
        var selectValue = $(this).chosen().val();
        $('#edit-start-year').val(null).trigger('chosen:updated');
        $('#edit-credits').val(null).trigger('chosen:updated');
        $('#edit-academic-year').val(null).trigger('chosen:updated');

        if (selectValue.length > 0) {
          if (currentStudent) {
            $('.dutc-year').show();
            $('.dutc-academic-year').hide();
          }
          else {
            $('.dutc-year').hide();
            $('.dutc-academic-year').show();
          }
        }
        else {
          $('.dutc-year').hide();
          $('.dutc-academic-year').hide();
        }
        $('.dutc-credits').hide();
        $('.dutc-cost').hide();
        $('.dutc-no-cost').hide();
      });

      $('#edit-start-year').change(function() {
        var selectValue = $(this).chosen().val();
        $('#edit-credits').val(null).trigger('chosen:updated');
        $('#edit-academic-year').val(null).trigger('chosen:updated');
        $('.dutc-ayear-disclaimer').hide();

        if (selectValue.length > 0) {
          $('.dutc-academic-year').show();
          $('.dutc-credits').hide();
          $('.dutc-cost').hide();
          $('.dutc-no-cost').hide();
        }
        else {
          $('.dutc-academic-year').hide();
          $('.dutc-credits').hide();
          $('.dutc-cost').hide();
          $('.dutc-no-cost').hide();
        }
      });

      $('#edit-academic-year').change(function() {
        var selectValue = $(this).chosen().val(),
          startYear = null;
        $('#edit-credits').val(null).trigger('chosen:updated');
        $('.dutc-ayear-disclaimer').hide();

        if (selectValue.length > 0) {
          var semester = $('#edit-semester').chosen().val(),
            semesterNum = 50,
            aYear = dutcSettings.currentAcademicYear;
          if (semester == 'winter') {
            semesterNum = 10;
          }
          else if (semester == 'spring') {
            semesterNum = 30;
          }
          else if (semester == 'fall') {
            semesterNum = 70;
          }
          if (selectValue == 'next') {
            aYear = dutcSettings.nextAcademicYear;
          }
          if (currentStudent && $('#edit-start-year').chosen().val().length > 0) {
            startYear = parseFloat($('#edit-start-year').chosen().val());
          }
          selectedProgramData = findProgramData(
            $('#edit-degree').chosen().val(),
            aYear,
            semesterNum,
            startYear
          );

          if (selectedProgramData != null) {
            if (selectedProgramData.details.billed_per_term == true) {
              calculateTuitionCost();
            }
            else if (
              currentStudent &&
              selectedProgramData.details.flat_rate == true &&
              (
                (semesterNum < 70 && startYear == 2020) ||
                startYear < 2020
              )
            ) {
              window.location.href = dutcSettings.flatRateLink;
            }
            else {
              $('.dutc-credits').show();
              $('.dutc-cost').hide();
              $('.dutc-no-cost').hide();
            }
          }
          else {
            calculateTuitionCost();
          }
        }
        else {
          $('.dutc-credits').hide();
          $('.dutc-cost').hide();
          $('.dutc-no-cost').hide();
        }
      });

      $('#edit-credits').change(function() {
        var selectValue = $(this).chosen().val();
        $('.dutc-ayear-disclaimer').hide();
        if (selectValue.length > 0) {
          calculateTuitionCost();
        }
        else {
          $('.dutc-cost').hide();
          $('.dutc-no-cost').hide();
        }
      });

    }
  };
})(jQuery, Drupal, drupalSettings);
