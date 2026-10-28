# Publishing a new version

1. Update `$pkgVersion` in the main `controller.php` file
2. Create a tag with the same value
3. Push the changes and the new tag
4. The `publish-release.yml` workflow will create a new GitHub release
