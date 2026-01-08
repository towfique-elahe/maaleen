(function ($) {
  "use strict";

  // Location Management
  window.wcLocation = {
    init: function () {
      this.bindEvents();
      this.checkLocationCookie();
    },

    bindEvents: function () {
      // Close modal
      $(document).on(
        "click",
        ".wc-location-modal__close, .wc-location-modal__overlay",
        function (e) {
          e.preventDefault();
          $("#wc-location-modal").fadeOut(300);
        }
      );

      // Location switcher buttons
      $(document).on("click", ".wc-location-btn", function (e) {
        e.preventDefault();
        var location = $(this).val();
        wcLocation.switchLocation(location);
      });

      // Location selector change
      $(document).on("change", ".wc-location-select", function () {
        var location = $(this).val();
        wcLocation.switchLocation(location);
      });

      // Location dropdown links
      $(document).on("click", ".wc-location-dropdown a", function (e) {
        e.preventDefault();
        var location =
          $(this).data("location") ||
          $(this)
            .attr("href")
            .match(/location=([^&]+)/)[1];
        wcLocation.switchLocation(location);
      });

      // Escape key to close modal
      $(document).on("keyup", function (e) {
        if (e.key === "Escape" && $("#wc-location-modal").is(":visible")) {
          $("#wc-location-modal").fadeOut(300);
        }
      });
    },

    checkLocationCookie: function () {
      if (
        !localStorage.getItem("wc_user_location") &&
        !document.cookie.match(/wc_user_location/)
      ) {
        // Show modal after delay
        setTimeout(function () {
          $("#wc-location-modal").fadeIn(300);
        }, 1000);
      }
    },

    switchLocation: function (location) {
      if (!["bd", "au"].includes(location)) {
        console.error("Invalid location:", location);
        return;
      }

      // Show loading state
      var $switcher = $(".wc-location-switcher, .wc-location-indicator");
      $switcher.addClass("wc-location-loading");

      // Update via AJAX
      $.ajax({
        url: wc_location_data.ajax_url,
        type: "POST",
        data: {
          action: "wc_switch_location",
          nonce: wc_location_data.nonce,
          location: location,
        },
        success: function (response) {
          if (response.success) {
            // Update UI
            wcLocation.updateUI(location);

            // Show success message
            wcLocation.showMessage(response.data.message, "success");

            // Reload page to update prices
            setTimeout(function () {
              location.reload();
            }, 800);
          } else {
            wcLocation.showMessage("Failed to update location", "error");
          }
        },
        error: function () {
          wcLocation.showMessage("Network error. Please try again.", "error");
        },
        complete: function () {
          $switcher.removeClass("wc-location-loading");
        },
      });
    },

    updateUI: function (location) {
      // Update cookie
      document.cookie =
        "wc_user_location=" + location + "; path=/; max-age=" + 86400 * 30;
      localStorage.setItem("wc_user_location", location);

      // Update current location indicator
      var locationNames = {
        bd: { flag: "🇧🇩", name: "Bangladesh" },
        au: { flag: "🇦🇺", name: "Australia" },
      };

      $(".wc-current-location .flag").text(locationNames[location].flag);
      $(".wc-current-location .wc-location-name").text(
        locationNames[location].name
      );

      // Update active button
      $(".wc-location-btn").removeClass("active");
      $('.wc-location-btn[value="' + location + '"]').addClass("active");

      // Update select value
      $(".wc-location-select").val(location);
    },

    showMessage: function (message, type) {
      var $message = $(
        '<div class="wc-location-message wc-location-message--' +
          type +
          '">' +
          "<p>" +
          message +
          "</p>" +
          "</div>"
      );

      $("body").append($message);

      // Animate in
      setTimeout(function () {
        $message.addClass("wc-location-message--visible");
      }, 10);

      // Remove after delay
      setTimeout(function () {
        $message.removeClass("wc-location-message--visible");
        setTimeout(function () {
          $message.remove();
        }, 300);
      }, 3000);
    },

    // Quick set function for modal buttons
    setWCLocation: function (location) {
      this.switchLocation(location);
    },
  };

  // Initialize
  $(document).ready(function () {
    wcLocation.init();
  });

  // Global function for onclick handlers
  window.setWCLocation = function (location) {
    wcLocation.setWCLocation(location);
  };
})(jQuery);

// Fallback for when jQuery isn't loaded yet
document.addEventListener("DOMContentLoaded", function () {
  // Add loading class to body for styling
  var style = document.createElement("style");
  style.textContent = `
        .wc-location-loading {
            opacity: 0.7;
            pointer-events: none;
            position: relative;
        }
        .wc-location-loading::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 20px;
            height: 20px;
            margin: -10px 0 0 -10px;
            border: 2px solid #f3f3f3;
            border-top: 2px solid #2271b1;
            border-radius: 50%;
            animation: wcSpin 1s linear infinite;
        }
        .wc-location-message {
            position: fixed;
            top: 20px;
            right: 20px;
            background: white;
            padding: 15px 20px;
            border-radius: 6px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.15);
            transform: translateX(150%);
            transition: transform 0.3s ease;
            z-index: 9999999;
            max-width: 300px;
        }
        .wc-location-message--visible {
            transform: translateX(0);
        }
        .wc-location-message--success {
            border-left: 4px solid #46b450;
        }
        .wc-location-message--error {
            border-left: 4px solid #dc3232;
        }
        @keyframes wcSpin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    `;
  document.head.appendChild(style);
});
