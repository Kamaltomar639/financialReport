var SignIn = function () {

    return {

        // =========================================================================
        // CONSTRUCTOR APP
        // =========================================================================
        init: function () {
            SignIn.signBackstretch();
        },

        // =========================================================================
        // BACKSTRETCH
        // =========================================================================
        signBackstretch: function () {
            // Duration is the amount of time in between slides,
            // and fade is value that determines how quickly the next image will fade in
            $.backstretch([
                'https://whats42nite.com/prodev/images/background_picture.png'
            ], {duration: 5000, fade: 750});
        }

    };

}();

// Call main app init
SignIn.init();