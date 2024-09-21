<?php

namespace IDAF;

class GithubRelease {

    protected $release_info;

    public function __construct(\stdClass $github_release_info) {
        $this->release_info = $github_release_info;        
    }
    public function __toString() {
        return $this->release_info->tag_name;
    }

    public function getDownloadUrl() {
        if (!empty($this->release_info->assets)) {
            $asset = reset($this->release_info->assets);
            return $asset->browser_download_url;
        }
    }
}