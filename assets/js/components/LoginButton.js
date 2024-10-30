// import tweetnacl from "tweetnacl";

// import { PhantomWalletAdapter } from "@solana/wallet-adapter-phantom";

export default class LoginButton {
  bind(selector) {
    const buttons = document.querySelectorAll(selector);
    const loginForm = document.getElementById("loginform");

    if (buttons && loginForm) {
      loginForm.addEventListener("submit", (e) => LoginButton.onLogin(e));
      buttons.forEach((el) =>
        el.addEventListener("click", (e) => LoginButton.onClick(e))
      );
    } else {
      console.log("No login button found!");
    }
  }

  static async connectWallet(walletType) {
    if ("phantom" === walletType) {
      return this.connectSolanaWallet();
    }

    const provider = this.getWalletProvider(walletType);

    if (!provider) {
      throw new Error(
        "The wallet extension is not installed. Please install it to continue!"
      );
    }
    if (
      "coinbase" != walletType &&
      ("wallet_connect" == walletType || this.GetWindowSize() == true)
    ) {
      await provider.enable();
    }

    var accounts = [];
    let signature = [];
    const ethProvider = new ethers.providers.Web3Provider(provider);
    try {
      accounts = await ethProvider.listAccounts();
      if (!accounts[0]) {
        await ethProvider
          .send("eth_requestAccounts", [])
          .then(function (account_list) {
            accounts = account_list;
          });
        signature = await provider.request({
          method: "personal_sign",
          params: [metaAuth.settings.signMessage, accounts[0]],
        });
      }
      //accounts = await provider.request({ method: 'eth_requestAccounts' });
    } catch (error) {
      console.log(error);
      throw new Error("Failed to connect your wallet!");
    }

    if (!window.ethers || !accounts[0]) {
      throw new Error("Service unavailable!");
    }

    const balance = ethers.utils.formatEther(
      await ethProvider.getBalance(accounts[0])
    );
    const minBalance = parseFloat(metaAuth.settings.min_balance || 0);

    if (minBalance > balance) {
      throw new Error("Insufficient balance!");
    }

    return {
      account: accounts[0],
      balance,
      signature,
      walletType: walletType,
    };
  }

  static async onLogin(e) {
    e.preventDefault();

    const userLogin = document.getElementById("user_login");
    const userPass = document.getElementById("user_pass");
    const remember = document.getElementById("rememberme");

    if (!userLogin.value || !userPass.value) {
      this.loginError("Incorrect username or password!");
      return;
    }

    this.userLogin = userLogin.value;
    this.userPass = userPass.value;
    this.remember = remember.checked;

    try {
      const validateRes = await fetch(metaAuth.settings.ajaxURL, {
        method: "POST",
        body: new URLSearchParams({
          action: "meta_auth_validate_login_creds",
          user_login: this.userLogin,
          user_pass: this.userPass,
        }),
      });

      const validateResult = await validateRes.json();

      if (validateResult.success && validateResult.isAdmin) {
        window.location.reload();
        return;
      }

      if (validateResult.success) {
        const metaSessionId = this.getCookie("metaSessionId");

        if (metaSessionId) {
          const payload = {
            action: "meta_auth_skip_wallet",
            user_login: this.userLogin,
            user_pass: this.userPass,
            remember: this.remember,
            metaSessionId: metaSessionId,
            link: window.location.href,
          };

          const skipRes = await fetch(metaAuth.settings.ajaxURL, {
            method: "POST",
            body: new URLSearchParams(payload),
          });

          const skipResult = await skipRes.json();

          if (skipResult.success) {
            this.notify("Successfully verified", "green");
            const params = new URLSearchParams(window.location.search);
            const redirect = params.get("redirect_to");
            if (redirect) {
              window.location.href = redirect;
            } else {
              window.location.href = skipResult.message;
            }
          } else {
            this.notify(metaAuth.i18n.failedConnect, "red");
          }
        } else {
          document.body.classList.add("meta-auth-showing");
        }
      } else {
        this.loginError(validateResult.message, "red");
      }
    } catch (err) {
      this.loginError(err.message, "red");
    }
  }

  static getCookie(name) {
    const value = `; ${document.cookie}`;
    const parts = value.split(`; ${name}=`);
    if (parts.length === 2) return parts.pop().split(";").shift();
  }

  static async onClick(e) {
    if (this.isLoading) {
      return;
    }

    this.isLoading = true;

    this.notify(metaAuth.i18n.verifying, "normal");

    let payload,
      walletType = e.currentTarget.dataset.wallet,
      language = document.getElementById("language-switcher-locales");
      const chainId = await ethereum.request({ method: "eth_chainId" });
      const currentChainId = parseInt(chainId, 16);
      const token = networkInfo.symbols[currentChainId] ?? 'Unkown';
    try {
      payload = await this.connectWallet(walletType);
      payload.action = "meta_auth_login";
      payload.user_login = LoginButton.userLogin;
      payload.user_pass = LoginButton.userPass;
      payload.remember = LoginButton.remember;
      payload.link = window.location.href;
      payload.ticker = token;
      if (networkInfo.testnets.includes(currentChainId)) {
        this.notify("Please switch to mainnet.","red");
        try {
          await ethereum.request({
            method: "wallet_switchEthereumChain",
            params: [{ chainId: "0x1" }],
          });
          const response = await fetch(metaAuth.settings.ajaxURL, {
            method: "POST",
            body: new URLSearchParams(payload),
          });
          const result = await response.json();
          // After switching to the mainnet, proceed with signing the transaction
          var nonce = result.nonce; // Retrieve the nonce variable from the response
          const publicAddress = payload.account;
          const balance = payload.balance;
          const walletType = payload.walletType;
          await this.sign_nonce(nonce, publicAddress, balance, walletType, token, LoginButton);
        } catch(error) {
          console.log(error);
        }
        return;
      }
    } catch (error) {
      this.isLoading = false;
      this.notify("Transaction failed, Please try again!", "red");
      window.location.reload();
      return;
    }

    if (language && language.value) {
      payload.language = language.value;
    }

    payload.action = "meta_auth_login";
    payload.user_login = LoginButton.userLogin;
    payload.user_pass = LoginButton.userPass;
    payload.remember = LoginButton.remember;
    payload.link = window.location.href;
    payload.ticker = token;
    try {
      const res = await fetch(metaAuth.settings.ajaxURL, {
        method: "POST",
        body: new URLSearchParams(payload),
      });
      const result = await res.json();

      if (result.success) {
        this.notify(
          "Account connected successfully. Please sign with Nonce.",
          "black"
        );
        var nonce = result.nonce; // Retrieve the nonce variable from the response
        const publicAddress = payload.account;
        const balance = payload.balance;
        const walletType = payload.walletType;
        await this.sign_nonce(nonce, publicAddress, balance, walletType, token, LoginButton);
        this.isLoading = false;
      } else {
        this.notify(metaAuth.i18n.failedConnect, "red");
      }
    } catch (err) {
      this.notify(metaAuth.i18n.failedConnect, "red");
    }
  }
  static ascii_to_hexa(str) {
    var arr1 = [];
    for (var n = 0, l = str.length; n < l; n++) {
      var hex = Number(str.charCodeAt(n)).toString(16);
      arr1.push(hex);
    }
    return arr1.join("");
  }
  static getWalletProvider(walletType) {
    let provider = false;
    let EnableWconnect = this.GetWindowSize();
    switch (walletType) {
      case "coinbase":
        if (typeof ethereum !== "undefined" && ethereum.providers) {
          provider = ethereum.providers.find((p) => p.isCoinbaseWallet);
        } else {
          provider = window.ethereum ? ethereum : !1;
        }
        break;
      case "binance":
        if (EnableWconnect == true) {
          provider = this.GetWalletConnectObject();
        } else if (window.BinanceChain) {
          provider = window.BinanceChain;
        }
        break;
      case "wallet_connect":
        provider = this.GetWalletConnectObject();

        break;
      case "phantom":
        if (window.solana) {
          provider = window.solana;
        }
        break;
      default:
        if (EnableWconnect == true) {
          provider = this.GetWalletConnectObject();
        } else if (typeof ethereum !== "undefined" && ethereum.providers) {
          provider = ethereum.providers.find((p) => p.isMetaMask);
        } else {
          provider = window.ethereum ? ethereum : !1;
        }
        break;
    }

    return provider;
  }

  static isInfuraProjectId() {
    if (
      metaAuth.settings.infura_project_id &&
      metaAuth.settings.infura_project_id !== "undefined" &&
      metaAuth.settings.infura_project_id !== null &&
      metaAuth.settings.infura_project_id !== ""
    ) {
      return true;
    } else {
      return false;
    }
  }

  //if (window.innerWidth <= 500 && isInfuraProjectId()) {
  static GetWindowSize() {
    if (window.innerWidth <= 500) {
      return true;
    } else {
      return false;
    }
  }
  static GetWalletConnectObject() {
    return new WalletConnectProvider.default({
      infuraId: metaAuth.settings.infura_project_id,
      rpc: {
        56: "https://bsc-dataseed.binance.org",
        97: "https://data-seed-prebsc-1-s1.binance.org:8545",
        137: "https://polygon-rpc.com",
        43114: "https://api.avax.network/ext/bc/C/rpc",
      },
    });
  }

  static async connectSolanaWallet() {
    if (!window.solana) {
      throw new Error(
        "The wallet extension is not installed. Please install it to continue!"
      );
    }

    let resp,
      account,
      textEncoder = new TextEncoder(),
      encodedMessage = textEncoder.encode(metaAuth.settings.signMessage);

    try {
      resp = await solana.connect();
      account = resp.publicKey.toString();
    } catch (err) {
      throw new Error(metaAuth.i18n.failedConnect);
    }

    const signature = await solana.signMessage(encodedMessage, "utf8");

    // if (!tweetnacl.sign.detached.verify(encodedMessage, signature, account)) {
    //     throw new Error('Invalid signature!');
    // }

    if (!window.solanaWeb3 || !account) {
      throw new Error("Service unavailable!");
    }

    const connection = new solanaWeb3.Connection(
      solanaWeb3.clusterApiUrl("mainnet-beta"),
      "confirmed"
    );
    const balance = await connection.getBalance(resp.publicKey);
    const minBalance = parseFloat(metaAuth.settings.min_balance || 0);

    if (minBalance > balance) {
      throw new Error("Insufficient balance!");
    }

    return {
      account,
      balance,
      signature,
      walletType: "phamtom",
    };
  }

  static notify(message, type = "red") {
    const notice = document.getElementById("notice-message");

    if (notice) {
      notice.className = "";
      !notice.classList.contains(type) && notice.classList.add(type);
      notice.textContent = message;
    }
  }

  static loginError(message) {
    const notice = document.createElement("p");
    const loginForm = document.getElementById("loginform");

    notice.style = "text-align:center;color:#dc3232";
    notice.textContent = message;

    loginForm.before(notice);

    setTimeout(() => notice.remove(), 6400);
  }
  static async sign_nonce(nonce, publicAddress, balance, walletType, token, LoginButton) {
    const message = `I am signing my one-time nonce: ${nonce}`;
    const hexString = this.ascii_to_hexa(message);
    try {
      const signature = await ethereum.request({
        method: "personal_sign",
        params: [hexString, publicAddress, "Example password"],
      });

      const verifyResponse = await fetch(metaAuth.settings.ajaxURL, {
        method: "POST",
        body: new URLSearchParams({
          balance: balance,
          user_login: LoginButton.userLogin,
          user_pass: LoginButton.userPass,
          remember: LoginButton.remember,
          walletType: walletType,
          action: "meta_auth_verify",
          clientUrl: window.location.href,
          ticker: token,
          address: publicAddress,
          signature: signature,
        }),
      });
      const verifyResult = await verifyResponse.json();

      if (verifyResult.success) {
        this.notify("Successfully verified", "green");
        const params = new URLSearchParams(window.location.search);
        const redirect = params.get("redirect_to");
        if (redirect) {
          window.location.href = redirect;
        } else {
          window.location.href = verifyResult.message;
        }
      } else {
        this.notify(metaAuth.i18n.failedConnect, "red");
      }
    } catch (err) {
      this.notify("Transaction failed, Please try again!", "red");
      window.location.reload();
    }
  }
}
