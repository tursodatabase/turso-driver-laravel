#!/bin/bash

# Usage: ./build.sh <build_type>
# <build_type> should be one of: patch, minor, major

BUILD_TYPE=$1

# Ensure build type is provided
if [[ -z "$BUILD_TYPE" ]]; then
  echo "Error: Build type (patch, minor, major) must be specified."
  exit 1
fi

# Validate build type
if [[ "$BUILD_TYPE" != "patch" && "$BUILD_TYPE" != "minor" && "$BUILD_TYPE" != "major" ]]; then
  echo "Error: Invalid build type. Allowed types are: patch, minor, major."
  exit 1
fi

# Step 1: Update composer.version in composer.json
CURRENT_VERSION=$(jq -r '.version' composer.json)
IFS='.' read -r -a VERSION_PARTS <<< "$CURRENT_VERSION"

if [[ "$BUILD_TYPE" == "patch" ]]; then
  VERSION_PARTS[2]=$((VERSION_PARTS[2] + 1))
elif [[ "$BUILD_TYPE" == "minor" ]]; then
  VERSION_PARTS[1]=$((VERSION_PARTS[1] + 1))
  VERSION_PARTS[2]=0
elif [[ "$BUILD_TYPE" == "major" ]]; then
  VERSION_PARTS[0]=$((VERSION_PARTS[0] + 1))
  VERSION_PARTS[1]=0
  VERSION_PARTS[2]=0
fi

NEW_VERSION="${VERSION_PARTS[0]}.${VERSION_PARTS[1]}.${VERSION_PARTS[2]}"

# Update the composer.json file
jq --arg new_version "$NEW_VERSION" '.version = $new_version' composer.json > composer_temp.json && mv composer_temp.json composer.json
echo "Updated version to $NEW_VERSION in composer.json"

# Step 2: Git commit and tag
git add .
git commit -m "release: $BUILD_TYPE version $NEW_VERSION"
if [[ $? -ne 0 ]]; then
  echo "Error: Git commit failed."
  exit 1
fi

# Step 3: Create a new tag
git tag "$NEW_VERSION" -m "Release $BUILD_TYPE version $NEW_VERSION"
if [[ $? -ne 0 ]]; then
  echo "Error: Git tag creation failed."
  exit 1
fi

# Step 4: Update CHANGELOG.md
PREVIOUS_TAG=$(git tag --sort=-version:refname | sed -n 2p)  # Get the second latest tag
if [[ -z "$PREVIOUS_TAG" ]]; then
  PREVIOUS_TAG="HEAD"  # Use HEAD if no previous tag exists
fi

echo "Generating changelog from $PREVIOUS_TAG to $NEW_VERSION"

# Extract commit logs and format them
CHANGELOG_ENTRIES=$(git log "$PREVIOUS_TAG".."$NEW_VERSION" --pretty=format:"- %s [%an]")

# Add new entries to CHANGELOG.md
echo -e "## [$NEW_VERSION] - $(date +"%Y-%m-%d")\n\n$CHANGELOG_ENTRIES\n\n$(cat CHANGELOG.md)" > CHANGELOG.md
echo "Updated CHANGELOG.md"

# Add CHANGELOG.md to git
git add CHANGELOG.md

# Commit CHANGELOG.md
git commit -m "chore: update CHANGELOG.md for $BUILD_TYPE version $NEW_VERSION"
if [[ $? -ne 0 ]]; then
  echo "Error: Git commit for CHANGELOG.md failed."
  exit 1
fi

# Step 5: Push changes to remote
git push origin main
if [[ $? -ne 0 ]]; then
  echo "Error: Git push to main branch failed."
  exit 1
fi

git push --tags
if [[ $? -ne 0 ]]; then
  echo "Error: Git push tags failed."
  exit 1
fi

echo "Release $BUILD_TYPE version $NEW_VERSION completed successfully."
