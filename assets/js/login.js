import LoginButton from "./components/LoginButton";
import LazyScriptsLoader from "./components/LazyScriptsLoader";

window.addEventListener('DOMContentLoaded', () => {
    const loginButton = new LoginButton();
    const lazyScriptsLoader = new LazyScriptsLoader(
        ['load', 'keydown', 'mousemove', 'touchmove', 'touchstart', 'touchend', 'wheel'],
        [
            {
                id: "ethers",
                uri: metaAuth.settings.pluginURI + 'assets/js/vendor/ethers.min.js',
            },
            {
                id: "solana",
                uri: metaAuth.settings.pluginURI + 'assets/js/vendor/solana.min.js',
            },
            {
                id: "wallet_connect",
                uri: metaAuth.settings.pluginURI + 'assets/js/vendor/walletconnect.js'
            },
        ]
    );

    loginButton.bind(".metaAuthLoginBtn");
    lazyScriptsLoader.init(lazyScriptsLoader);
});
