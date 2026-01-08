(function ($) {
  "use strict";

  // Location Management
  window.wcLocation = {
    init: function () {
      this.bindEvents();
      // Remove auto-check on init since we handle it in PHP
    },

    bindEvents: function () {
      // Close modal
      $(document).on(
        "click",
        ".wc-location-modal__close, .wc-location-modal__overlay",
        function (e) {
          e.preventDefault();
          wcLocation.hideModal();
        }
      );

      // Trigger button click
      $(document).on(
        "click",
        "#wc-change-location-trigger, .wc-change-location-btn",
        function (e) {
          e.preventDefault();
          wcLocation.showModal();
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
          wcLocation.hideModal();
        }
      });

      // Prevent modal close when clicking inside content
      $(document).on("click", ".wc-location-modal__content", function (e) {
        e.stopPropagation();
      });
    },

    showModal: function () {
      $("#wc-location-modal").css("display", "flex");
      $("body").addClass("wc-location-modal-open");
      // Animate in
      setTimeout(function () {
        $(".wc-location-modal__content").addClass("wc-modal-visible");
      }, 10);
    },

    hideModal: function () {
      $(".wc-location-modal__content").removeClass("wc-modal-visible");
      setTimeout(function () {
        $("#wc-location-modal").fadeOut(300);
        $("body").removeClass("wc-location-modal-open");
      }, 200);
    },

    switchLocation: function (location) {
      if (!["bd", "au"].includes(location)) {
        console.error("Invalid location:", location);
        return;
      }

      // Close modal after selection
      this.hideModal();

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

            // RELOAD THE PAGE AFTER SUCCESS
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

      // Update button text if dynamic button is used
      $(".wc-change-location-btn").each(function () {
        var $btn = $(this);
        if ($btn.find(".wc-current-location-text").length) {
          $btn
            .find(".wc-current-location-text")
            .html(
              locationNames[location].flag + " " + locationNames[location].name
            );
        }
      });
    },

    updatePrices: function () {
      if (typeof wc_location_data.update_prices_url === "undefined") {
        window.location.reload();
        return;
      }

      var productIds = [];

      $("[data-product-id]").each(function () {
        var id = $(this).data("product-id");
        if (id) productIds.push(id);
      });

      if (!productIds.length) return;

      $.ajax({
        url: wc_location_data.update_prices_url,
        type: "GET",
        data: {
          product_ids: productIds.join(","),
        },
        success: function (response) {
          if (!response.success || !response.data.prices) return;

          $(".product-stock").load(
            window.location.href + " .product-stock > *"
          );

          $.each(response.data.prices, function (productId, priceHtml) {
            $('[data-product-id="' + productId + '"]').html(priceHtml);
          });

          // Trigger WooCommerce refresh hooks
          $(document.body).trigger("wc_fragment_refresh");
          $(document.body).trigger("wc_price_updated");
        },
      });
    },

    clearCart: function () {
      if (confirm("Switching location will clear your cart. Continue?")) {
        $.ajax({
          url: wc_location_data.ajax_url,
          type: "POST",
          data: {
            action: "wc_clear_cart",
            nonce: wc_location_data.nonce,
          },
          success: function () {
            $(".cart-contents").html("0");
            $(".woocommerce-cart-form").remove();
            $(".cart-empty").show();
            $(document).trigger("wc_location_changed", [location]);
          },
        });
      }
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
        .wc-modal-visible {
            transform: translateY(0) !important;
            opacity: 1 !important;
        }
        @keyframes wcSpin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    `;
  document.head.appendChild(style);
});
