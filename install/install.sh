#!/usr/bin/env bash

#############################################################
# SquareOne Global Docker Installer
#############################################################

SCRIPTDIR=$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd );
PHAR_NAME="so.phar"
CONFIG_DIR=~/.config/squareone
BIN_NAME="so"
DC_VERSION="1.29.2"
NVM_VERSION="0.38.0"
AUTOCOMPLETE_BASH="squareone.autocompletion"
AUTOCOMPLETE_ZSH="squareone_completion.zsh"
AUTOCOMPLETE_FISH="so.fish"

# if the SO_DEV environment variable is set and to run a dev install
is_dev() {
  if [[ -n "${SO_DEV}" ]]; then
    return 0
  fi

  return 1
}

if is_dev; then
    echo "***** Installing development version"
    BIN_NAME="sodev"
    AUTOCOMPLETE_BASH="squareone.dev.autocompletion"
    AUTOCOMPLETE_ZSH="squareone_dev_completion.zsh"
    AUTOCOMPLETE_FISH="so.dev.fish"
fi

create_config_folder() {
  if [[ ! -d "${CONFIG_DIR}" ]]; then
    mkdir -p ${CONFIG_DIR}
  fi
}

install_homebrew() {
  bash -c "$(curl -fsSL https://raw.githubusercontent.com/Homebrew/install/master/install.sh)"
}

enable_autocomplete() {
  if [[ "$OSTYPE" == "darwin"* ]]; then
    sudo curl -fsSL 'https://raw.githubusercontent.com/moderntribe/square1-global-docker/master/squareone.autocompletion' -o "$(brew --prefix)/etc/bash_completion.d/${AUTOCOMPLETE_BASH}"
    curl -fsSL 'https://raw.githubusercontent.com/moderntribe/square1-global-docker/master/squareone.autocompletion.zsh' -o ~/."${AUTOCOMPLETE_ZSH}" && echo "source ~/.${AUTOCOMPLETE_ZSH}" >> ~/.zshrc
  else
    sudo curl -fsSL 'https://raw.githubusercontent.com/moderntribe/square1-global-docker/master/squareone.autocompletion' -o /etc/bash_completion.d/"${AUTOCOMPLETE_BASH}"
    if [[ -d "~/.config/fish/completions" ]] ; then
        curl -fsSL 'https://raw.githubusercontent.com/moderntribe/square1-global-docker/master/squareone.autocompletion.fish' -o ~/.config/fish/completions/"${AUTOCOMPLETE_FISH}"
    fi
  fi
}

is_wsl() {
    [ -n "${WSL_DISTRO_NAME}" ]
}

install_phar() {
  PHAR_DOWNLOAD=$(curl -s https://api.github.com/repos/moderntribe/square1-global-docker/releases/latest \
        | grep browser_download_url \
        | grep ${PHAR_NAME} \
        | cut -d '"' -f 4)

  if [[ -z "${PHAR_DOWNLOAD}" ]] ; then
    echo 'Error connecting to the GitHub API, enter a GitHub token to try again (you can create a new one here https://github.com/settings/tokens/new):';
    read -r GITHUB_TOKEN

    PHAR_DOWNLOAD=$(curl -H "Authorization: token ${GITHUB_TOKEN}" -s https://api.github.com/repos/moderntribe/square1-global-docker/releases/latest \
      | grep browser_download_url \
      | grep ${PHAR_NAME} \
      | cut -d '"' -f 4)

    if [[ -z "${PHAR_DOWNLOAD}" ]] ; then
      echo "Whoops, we still can't connect. Try manually downloading so.phar from the releases page: https://github.com/moderntribe/square1-global-docker/releases"
      exit 1;
    fi
  fi

  curl -fsSL --create-dirs "${PHAR_DOWNLOAD}" -o "${CONFIG_DIR}/bin/${BIN_NAME}"
  chmod +x "${CONFIG_DIR}/bin/${BIN_NAME}"
  sudo ln -s "${CONFIG_DIR}/bin/${BIN_NAME}" "/usr/local/bin/${BIN_NAME}"
}

symlink_sq1_dev() {
    SO_PATH=$(realpath "${SCRIPTDIR}/../so")
    SO_TARGET="/usr/local/bin/sodev"

    if [[ -e "${SO_TARGET}" ]]; then
        sudo rm -rf "${SO_TARGET}"
    fi

    sudo ln -fs "${SO_PATH}" "${SO_TARGET}"
}

install_nvm() {
    curl -o- "https://raw.githubusercontent.com/nvm-sh/nvm/v${NVM_VERSION}/install.sh" | bash
}

echo "* Creating config folder: ~/.config/squareone"
create_config_folder

# OSX
if [[ "$OSTYPE" == "darwin"* ]]; then
  command -v docker >/dev/null 2>&1
  if [[ $? != 0 ]] ; then
      echo "Docker appears to be missing, install it from here: https://hub.docker.com/editions/community/docker-ce-desktop-mac"
      exit 1;
  fi

  command -v brew >/dev/null 2>&1
  if [[ $? != 0 ]] ; then
      echo "Homebrew not found, do you want to install it?"
      select yn in "Yes" "No"; do
          case $yn in
              Yes ) install_homebrew; break;;
              No ) exit;;
          esac
      done
  fi

  echo "* Installing dependencies via brew..."
  curl -fsSL https://raw.githubusercontent.com/moderntribe/square1-global-docker/master/brew/packages.txt -o "${CONFIG_DIR}/packages.txt"
  brew install "$(<${CONFIG_DIR}/packages.txt)"
  echo "* Setting the default PHP version to 7.4..."
  brew link php@7.4 --force
fi

# Debian Linux flavors
if [[ -x "$(command -v apt-get)" ]]; then
    echo "* Installing dependencies via apt, enter your sudo password when requested..."

    echo "* Removing legacy docker installs..."
    sudo apt-get remove docker docker-engine docker.io containerd runc

    echo "* Preparing docker sources..."
    sudo apt-get install -y \
        apt-transport-https \
        ca-certificates \
        curl \
        gnupg \
        lsb-release
    curl -fsSL https://download.docker.com/linux/ubuntu/gpg | sudo gpg --dearmor -o /usr/share/keyrings/docker-archive-keyring.gpg
    echo \
      "deb [arch=amd64 signed-by=/usr/share/keyrings/docker-archive-keyring.gpg] https://download.docker.com/linux/ubuntu \
      $(lsb_release -cs) stable" | sudo tee /etc/apt/sources.list.d/docker.list > /dev/null

    echo "* Updating and upgrading apt..."
    sudo apt-get update -y && sudo apt-get upgrade -y
    curl -fsSL https://raw.githubusercontent.com/moderntribe/square1-global-docker/master/install/debian/apt.txt -o "${CONFIG_DIR}/apt.txt"

    echo "* Installing docker-compose..."
    sudo curl -fsSL "https://github.com/docker/compose/releases/download/${DC_VERSION}/docker-compose-$(uname -s)-$(uname -m)" -o /usr/local/bin/docker-compose
    sudo chmod +x /usr/local/bin/docker-compose

    echo "* Installing packages..."
    xargs -a "${CONFIG_DIR}/apt.txt" sudo apt-get install -y

    echo "* Installing docker-compose bash completion"
    sudo curl \
        -L https://raw.githubusercontent.com/docker/compose/1.29.2/contrib/completion/bash/docker-compose \
        -o /etc/bash_completion.d/docker-compose

    echo "* Installing nameservers to /etc/resolv.conf.head..."
    sudo curl -fsSL https://raw.githubusercontent.com/moderntribe/square1-global-docker/master/install/debian/resolv.conf.head -o /etc/resolv.conf.head

    echo "* Backing up /etc/NetworkManager/NetworkManager.conf and creating a version that uses openresolv..."
    if [[ -f "/etc/NetworkManager/NetworkManager.conf" ]]; then
        sudo mv /etc/NetworkManager/NetworkManager.conf /etc/NetworkManager/NetworkManager.conf.bak
    fi
    sudo curl -fsSL https://raw.githubusercontent.com/moderntribe/square1-global-docker/master/install/debian/NetworkManager.conf -o /etc/NetworkManager/NetworkManager.conf

    if ! is_wsl; then
        echo "* Disabling systemd-resolved DNS service..."
        sudo systemctl disable systemd-resolved
        sudo systemctl stop systemd-resolved
    fi

    # Windows Subsystem
    if is_wsl; then
        echo "* Detected Windows Subsystem..."
        echo "* Allowing the docker service and resolvconf to be started by all users. Editing /etc/sudoers"
        echo "%sudo ALL=(ALL) NOPASSWD: /usr/sbin/service docker start" | sudo tee -a /etc/sudoers
        echo "%sudo ALL=(ALL) NOPASSWD: /usr/sbin/resolvconf *" | sudo tee -a /etc/sudoers
        
        echo "* Adding docker start up commands to ~/.bashrc. Copy this over if you change shells"
        echo "# Start docker service if not running" >> ~/.bashrc
        echo "DOCKER_RUNNING=$(ps aux | grep dockerd | grep -v grep)" >> ~/.bashrc
        echo 'if [ -z "$DOCKER_RUNNING" ]; then' >> ~/.bashrc
        echo "  sudo service docker start" >> ~/.bashrc
        echo "fi" >> ~/.bashrc
        echo "sudo resolvconf -u" >> ~/.bashrc
        
        echo "* Disabling Windows DNS via custom /etc/wsl.conf..."
        sudo curl -fsSL https://raw.githubusercontent.com/moderntribe/square1-global-docker/master/install/wsl/wsl.conf -o /etc/wsl.conf
        sleep 8
        
        if [[ -L "/etc/resolv.conf" ]]; then
            echo "* Removing /etc/resolv.conf symlink..."
            sudo unlink /etc/resolv.conf
        fi
    fi

    echo "* Generating a new /etc/resolv.conf..."
    sudo resolvconf -u

    echo "* Fixing docker permissions"
    sudo usermod -a -G docker "${USER}"
fi

echo "* Installing nvm"
install_nvm

echo "* Enabling SquareOne autocompletion, enter your password when requested."
enable_autocomplete

if is_dev; then
    echo "* Running composer install..."
    composer install -d "${SCRIPTDIR}/../"

    echo "* Symlinking ./so binary to /usr/local/bin/sodev, enter your password when requested."
    symlink_sq1_dev
else
    echo "* Downloading so.phar to /usr/local/bin/so, enter your password when requested."
    install_phar
fi

# run SquareOne Global docker
${BIN_NAME}

echo ""
echo "************************************"
echo "If everything went smoothly, you should see the '${BIN_NAME}' command list above. Reboot to properly complete installation."
echo "************************************"
if is_wsl; then
    echo "* Start a new powershell in Widows and run: wsl --shutdown and then wsl to restart the VM."
else
    echo "* Reboot now to complete the installation [y/n]?"
    read -r CHOICE
    if [[ ${CHOICE} == y* ]]; then
        sudo reboot
    else
        echo "* Done! Make sure you reboot to complete the installation."
    fi
fi
