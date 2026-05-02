import re
from pathlib import Path
import datetime
import requests.exceptions
from github import Github
from github.GithubException import GithubException, UnknownObjectException
import os
import os.path
import time
import subprocess
import secrets

def check_input(key: str) -> bool:
    """
    Checks if a given key was passed in as an input variable
    """
    return f'{key}' in os.environ and os.environ[f'{key}'] != ""

def get_boolean(key: str) -> bool:
    """
    Parses an environment variable as a boolean
    """
    env = os.environ[f'INPUT_{key}'].lower()
    if env == "true":
        return True
    elif env == "false":
        return False
    else:
        print(f"::error::❌ Invalid '{key.lower()}' input argument: '{os.environ['INPUT_{key}']}'")
        exit(1)

def run_command(cmd: list[str], end_group: bool = False):
    """
    Runs a given command, surrounding output with ::stop-commands::
    :param cmd: command to run
    :param end_group: whether to run "::endgroup::" before exiting
    """
    token = secrets.token_urlsafe(32)
    print(f"::debug::Running {cmd}")
    proc = subprocess.Popen(cmd, stdout=subprocess.PIPE, stderr=subprocess.STDOUT)
    out, _ = proc.communicate()
    print(f"::stop-commands::{token}")
    print(out.decode())
    if proc.returncode != 0:
        print(f"::{token}::")
        if end_group:
            print("::endgroup::")
        print(f"::error::❌ Command {cmd} returned with non-zero exit code!")
        exit(proc.returncode)
    print(f"::{token}::")

# Read inputs & put them into variables
if not check_input("GITHUB_TOKEN"):
    print("::error::❌ Missing required input: GITHUB_TOKEN")
    exit(1)
token = os.environ['GITHUB_TOKEN']

if not check_input("RELEASE_TAG"):
    print("::error::❌ Missing required input: RELEASE_TAG")
    exit(1)
tag_name = os.environ['RELEASE_TAG']

# load plugin file
txt = Path('tsjippy-shared-functionality.php').read_text()

# get old version
try:
    oldVersion = re.search(r'\* Version:[ \t]*([\d.]+)', txt).group(1)
except Exception as e:
    exit()

# replace with new
txt = txt.replace(oldVersion, tag_name)

# Write changes
f = open('tsjippy-shared-functionality.php', "w")
f.write(txt)
f.close()

# Update the changelog with the new release

file    = 'CHANGELOG.md'

# load plugin file
changelog = Path(file).read_text()

# Get the whole unrelease section
try:
    total       = re.search(r'## \[Unreleased\] - yyyy-mm-dd([\s\S]*?)## \[', changelog).group(1)
    newTotal    = total

    # Remove empty sections
    for x in ["Added", "Changed", "Fixed", "Updated"]:
        pattern = r'(### '+x+'[\s\S]*'

        if(x != 'Updated'):
            pattern = pattern+'?)###'
        else:
            pattern = pattern+')'

        added   = re.search(pattern, total).group(1)

        if(added.rstrip("\n") == '### '+x):
            newTotal    = newTotal.replace(added, '')

    # Update in changelog
    changelog   = changelog.replace(total, newTotal)
except Exception as e:
    pass

# Add new unreleased section
newSection  = "## [Unreleased] - yyyy-mm-dd\n\n### Added\n\n### Changed\n\n### Fixed\n\n### Updated\n\n## [" + tag_name + "] - " + datetime.datetime.now().strftime("%Y-%m-%d")+"\n"
changelog    = changelog.replace('## [Unreleased] - yyyy-mm-dd', newSection)

# Write changes
f = open(file, "w")
f.write(changelog)
f.close()

# 
# Create Release
# Copied from https://github.com/mini-bomba/create-github-release
#

# A workaround for the "dubious ownership" error
print('::debug::😩 Attempting a workaround for the "dubious ownership" git error')
run_command(["git", "config", "--global", "--add", "safe.directory", "/github/workspace"])

files = []

# Create Github object
github = Github(base_url=os.environ['GITHUB_API_URL'],
                login_or_token=os.environ['INPUT_TOKEN'],
                user_agent="mini-bomba/create-github-release")

# Get the repo
repo = github.get_repo(os.environ['GITHUB_REPOSITORY'])

# Check current release state
print("👀 Checking current state of the release")
release = None
try:
    release = repo.get_release(tag_name)
except UnknownObjectException:
    release = None

body = newTotal
if release is not None:
    print("👌 Release found, copying missing input data")
else:
    print("❗ Release does not exists (yet)")
    if body is None:
        print("::error::Input parameter 'body' must be passed if the release does not exist")
        exit(1)

print("::group::📦 Creating/Updating the release...")
if release is not None:
    print("📝 Updating data...")
    release.update_release(tag_name, body)

    if len(files) > 0:
        print("📨 Uploading new assets...")
        for file in files:
            for retry in range(1, 4):
                try:
                    release.upload_asset(file)
                    print(f"✅ Uploaded {file}")
                    break
                except (requests.exceptions.ConnectionError, GithubException) as e:
                    if isinstance(e, GithubException) and e.status != 422:
                        raise
                    if retry < 3:
                        print(f"::warning::⚠️ Got a connection error while trying to upload asset {file} "
                              f"(attempt {retry}), retrying. Error details: {type(e).__name__}: {e}")
                        time.sleep(2)
                        for asset in release.get_assets():
                            if asset.name == os.path.basename(file):
                                print(f"🗑 Deleting duplicate asset {asset.name}")
                                asset.delete_asset()
                    else:
                        print(f"::error::❌ Could not upload asset {file} due to connection errors! "
                              f"Error details: {type(e).__name__}: {e}")
                        raise
else:
    print("📝 Creating new release...")
    release = repo.create_git_release(tag_name, tag_name, body)
    if len(files) > 0:
        print("📨 Uploading assets...")
        for file in files:
            for retry in range(1, 4):
                try:
                    release.upload_asset(file)
                    print(f"✅ Uploaded {file}")
                    break
                except (requests.exceptions.ConnectionError, GithubException) as e:
                    if isinstance(e, GithubException) and e.status != 422:
                        raise
                    if retry < 3:
                        print(f"::warning::⚠️ Got a connection error while trying to upload asset {file} "
                              f"(attempt {retry}), retrying. Error details: {type(e).__name__}: {e}")
                        time.sleep(2)
                        for asset in release.get_assets():
                            if asset.name == os.path.basename(file):
                                print(f"🗑 Deleting duplicate asset {asset.name}")
                                asset.delete_asset()
                    else:
                        print(f"::error::❌ Could not upload asset {file} due to connection errors! "
                              f"Error details: {type(e).__name__}: {e}")
                        raise
print("::endgroup::")
print("👌😎 Release created!")