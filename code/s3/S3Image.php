<?php
class S3Image extends S3File {
	const ORIENTATION_SQUARE = 0;
	const ORIENTATION_PORTRAIT = 1;
	const ORIENTATION_LANDSCAPE = 2;
	
	static $casting = array(
		'Tag' => 'HTMLText',
	);

	/**
	 * The width of an image thumbnail in a strip.
	 * @var int
	 */
	public static $strip_thumbnail_width = 50;
	
	/**
	 * The height of an image thumbnail in a strip.
	 * @var int
	 */
	public static $strip_thumbnail_height = 50;
	
	/**
	 * The width of an image thumbnail in the CMS.
	 * @var int
	 */
	public static $cms_thumbnail_width = 100;
	
	/**
	 * The height of an image thumbnail in the CMS.
	 */
	public static $cms_thumbnail_height = 100;
	
	/**
	 * The width of an image thumbnail in the Asset section.
	 */
	public static $asset_thumbnail_width = 100;
	
	/**
	 * The height of an image thumbnail in the Asset section.
	 */
	public static $asset_thumbnail_height = 100;
	
	/**
	 * The width of an image preview in the Asset section.
	 */
	public static $asset_preview_width = 400;
	
	/**
	 * The height of an image preview in the Asset section.
	 */
	public static $asset_preview_height = 200;
	
	/**
	 * Set up template methods to access the transformations generated by 'generate' methods.
	 */
	public function defineMethods() {
		$methodNames = $this->allMethodNames();
		foreach($methodNames as $methodName) {
			if(substr($methodName,0,8) == 'generate') {
				$this->addWrapperMethod(substr($methodName,8), 'getFormattedImage');
			}
		}
		
		parent::defineMethods();
	}

	/**
	 * Return an XHTML img tag for this Image,
	 * or NULL if the image file doesn't exist on the filesystem.
	 * 
	 * @return string
	 */
	function getTag() {
		if($this->S3->getObjectInfo($this->getUploadBucket(), $this->FileName (), false)) {
			$url = $this->URL;
			$title = ($this->Title) ? $this->Title : $this->Filename ();
			if($this->Title) {
				$title = Convert::raw2att($this->Title);
			} else {
				if(preg_match("/([^\/]*)\.[a-zA-Z0-9]{1,6}$/", $title, $matches))
                                $title = Convert::raw2att($matches[1]);
			}
			return "<img src=\"$url\" alt=\"$title\" />";
		}
	}
	
	/**
	 * Return an XHTML img tag for this Image.
	 * 
	 * @return string
	 */
	function forTemplate() {
		return $this->getTag();
	}

	/**
	 * Given an array of filedata from the request, load up the meta data for the Image File
	 * and send it off to S3
	 *
	 * @param array $filedata The file data from the request
	 * @return boolean
	 */
	function loadUploadedImage($filedata) {
		return $this->loadUploaded($filedata);
	}
	
	public function SetWidth($width) {
	    return $this->getFormattedImage('SetWidth', $width);
	}
	
	public function SetHeight($height) {
		return $this->getFormattedImage('SetHeight', $height);
	}
	
	public function SetSize($width, $height) {
	    return $this->getFormattedImage('SetSize', $width, $height);
	}
	
	public function SetRatioSize($width, $height) {
		return $this->getFormattedImage('SetRatioSize', $width, $height);
	}
	
	public function generateSetRatioSize(GD $gd, $width, $height) {
		return $gd->resizeRatio($width, $height);
	}
	
	/**
	 * Resize this Image by width, keeping aspect ratio. Use in templates with $SetWidth.
	 * @return GD
	 */
	public function generateSetWidth(GD $gd, $width) {
		return $gd->resizeByWidth($width);
	}
	
	/**
	 * Resize this Image by height, keeping aspect ratio. Use in templates with $SetHeight.
	 * @return GD
	 */
	public function generateSetHeight(GD $gd, $height){
		return $gd->resizeByHeight($height);
	}
	
	/**
	 * Resize this Image by both width and height, using padded resize. Use in templates with $SetSize.
	 * @return GD
	 */
	public function generateSetSize(GD $gd, $width, $height) {
		return $gd->paddedResize($width, $height);
	}
	
	public function CMSThumbnail() {
		return $this->getFormattedImage('CMSThumbnail');
	}
	
	/**
	 * Resize this image for the CMS. Use in templates with $CMSThumbnail.
	 * @return GD
	 */
	function generateCMSThumbnail(GD $gd) {
		return $gd->paddedResize($this->stat('cms_thumbnail_width'),$this->stat('cms_thumbnail_height'));
	}
	
	/**
	 * Resize this image for preview in the Asset section. Use in templates with $AssetLibraryPreview.
	 * @return GD
	 */
	function generateAssetLibraryPreview(GD $gd) {
		return $gd->paddedResize($this->stat('asset_preview_width'),$this->stat('asset_preview_height'));
	}
	
	/**
	 * Resize this image for thumbnail in the Asset section. Use in templates with $AssetLibraryThumbnail.
	 * @return GD
	 */
	function generateAssetLibraryThumbnail(GD $gd) {
		return $gd->paddedResize($this->stat('asset_thumbnail_width'),$this->stat('asset_thumbnail_height'));
	}
	
	/**
	 * Resize this image for use as a thumbnail in a strip. Use in templates with $StripThumbnail.
	 * @return GD
	 */
	function generateStripThumbnail(GD $gd) {
		return $gd->croppedResize($this->stat('strip_thumbnail_width'),$this->stat('strip_thumbnail_height'));
	}
	
	function generatePaddedImage(GD $gd, $width, $height) {
		return $gd->paddedResize($width, $height);
	}

	/**
	 * Return an image object representing the image in the given format.
	 * This image will be generated using generateFormattedImage().
	 * The generated image is cached, to flush the cache append ?flush=1 to your URL.
	 * @param string $format The name of the format.
	 * @param string $arg1 An argument to pass to the generate function.
	 * @param string $arg2 A second argument to pass to the generate function.
	 * @return Image_Cached
	 */
	function getFormattedImage($format, $arg1 = null, $arg2 = null) {
		if($this->ID && $this->Filename () &&
		   $this->S3->getObjectInfo($this->getUploadBucket(), $this->FileName (), false)) {
			$cacheFile = $this->cacheFilename($format, $arg1, $arg2);
			
			if(!$this->S3->getObjectInfo($this->getUploadBucket(), basename ($cacheFile), false) ||
                           isset($_GET['flush'])) {
				$this->generateFormattedImage($format, $arg1, $arg2);
			}
			
			$cached = new S3Image_Cached($cacheFile);
			// Pass through the title so the templates can use it
			$cached->Title = $this->Title;
			return $cached;
		}
	}
	
	/**
	 * Return the filename for the cached image, given it's format name and arguments.
	 * @param string $format The format name.
	 * @param string $arg1 The first argument passed to the generate function.
	 * @param string $arg2 The second argument passed to the generate function.
	 * @return string
	 */
	function cacheFilename($format, $arg1 = null, $arg2 = null) {
		$folder = $this->ParentID ? $this->Parent()->Filename :
		"http://{$this->getUploadBucket()}.s3.amazonaws.com/";
		
		$format = $format.$arg1.$arg2;
		
		return $folder . "_resampled/$format-" . $this->Name;
	}
	
	/**
	 * Generate an image on the specified format. It will save the image
	 * at the location specified by cacheFilename(). The image will be generated
	 * using the specific 'generate' method for the specified format.
	 * @param string $format Name of the format to generate.
	 * @param string $arg1 Argument to pass to the generate method.
	 * @param string $arg2 A second argument to pass to the generate method.
	 */
	function generateFormattedImage($format, $arg1 = null, $arg2 = null) {
		$cacheFile = $this->cacheFilename($format, $arg1, $arg2);
	
		$gd = new GD($this->URL);
		
		if($gd->hasGD()){
			$generateFunc = "generate$format";		
			if($this->hasMethod($generateFunc)){
				$gd = $this->$generateFunc($gd, $arg1, $arg2);
				if($gd){

					$bucket = $this->getUploadBucket();
					$this->S3->putBucket($bucket, S3::ACL_PUBLIC_READ);
					$tempFile = tempnam (getTempFolder(), 's3images').File::get_file_extension ($cacheFile);
					$gd->writeTo ($tempFile);
					$this->S3->putObjectFile($tempFile, $bucket, '_resampled/'.basename ($cacheFile),
								     S3::ACL_PUBLIC_READ);
					unlink ($tempFile);
				}
	
			} else {
				USER_ERROR("Image::generateFormattedImage - Image $format function not found.",E_USER_WARNING);
			}
		}
	}
	
	/**
	 * Generate a resized copy of this image with the given width & height.
	 * Use in templates with $ResizedImage.
	 */
	function generateResizedImage($gd, $width, $height) {
		if(is_numeric($gd) || !$gd){
			USER_ERROR("Image::generateFormattedImage - generateResizedImage is being called by legacy code or gd is not set.",E_USER_WARNING);
		}else{
			return $gd->resize($width, $height);
		}
	}

	/**
	 * Generate a resized copy of this image with the given width & height, cropping to maintain aspect ratio.
	 * Use in templates with $CroppedImage
	 */
	function generateCroppedImage($gd, $width, $height) {
		return $gd->croppedResize($width, $height);
	}
	
	/**
	 * Remove all of the formatted cached images for this image.
	 * @return int The number of formatted images deleted
	 */
	public function deleteFormattedImages() {
		if(!$this->Filename ()) return 0;
		
		$numDeleted = 0;
		$methodNames = $this->allMethodNames();
		$cachedFiles = array();
		
		$folder = $this->ParentID ? $this->Parent()->Filename : "http://{$this->getUploadBucket()}.s3.amazonaws.com/";
		$cacheDir = $folder . '_resampled/';
		
		if(is_dir($cacheDir)) {
			if($handle = opendir($cacheDir)) {
				while(($file = readdir($handle)) !== false) {
					// ignore all entries starting with a dot
					if(substr($file, 0, 1) != '.' &&
					   is_file($cacheDir . $file)) {
						$cachedFiles[] = $file;
					}
				}
				closedir($handle);
			}
		}
		
		foreach($methodNames as $methodName) {
			if(substr($methodName, 0, 8) == 'generate') {
				$format = substr($methodName, 8);
				$pattern = '/^' . $format . '[^\-]*\-' . $this->Name . '$/i';
				foreach($cachedFiles as $cfile) {
					
					if(preg_match($pattern, $cfile)) {
						if($this->S3->getObjectInfo($this->getUploadBucket(),
						    basename ($cacheDir . $cfile), false)) {
							$bucket = $this->getUploadBucket();
							$this->S3->deleteObject ($bucket, $cfile);
							$numDeleted++;
						}
					}
				}
			}
		}
		
		return $numDeleted;
	}
	
	/**
	 * Get the dimensions of this Image.
	 * @param string $dim If this is equal to "string", return the dimensions in string form,
	 * if it is 0 return the height, if it is 1 return the width.
	 * @return string|int
	 */
	function getDimensions($dim = "string") {
		if($this->FileName ()) {
			$imagefile = $this->FileName ();
			if($this->S3->getObjectInfo($this->getUploadBucket(), $imagefile, false)) {
				$size = getimagesize($imagefile);
				return ($dim === "string") ? "$size[0]x$size[1]" : $size[$dim];
			} else {
				return ($dim === "string") ? "file '$imagefile' not found" : null;
			}
		}
	}

	/**
	 * Get the width of this image.
	 * @return int
	 */
	function getWidth() {
		return $this->getDimensions(0);
	}
	
	/**
	 * Get the height of this image.
	 * @return int
	 */
	function getHeight() {
		return $this->getDimensions(1);
	}
	
	/**
	 * Get the orientation of this image.
	 * @return ORIENTATION_SQUARE | ORIENTATION_PORTRAIT | ORIENTATION_LANDSCAPE
	 */
	function getOrientation() {
		$width = $this->getWidth();
		$height = $this->getHeight();
		if($width > $height) {
			return self::ORIENTATION_LANDSCAPE;
		} elseif($height > $width) {
			return self::ORIENTATION_PORTRAIT;
		} else {
			return self::ORIENTATION_SQUARE;
		}
	}
	
	protected function onBeforeDelete() { 
		parent::onBeforeDelete(); 

		$this->deleteFormattedImages();
	}
}
/**
 * A resized / processed {@link Image} object.
 * When Image object are processed or resized, a suitable Image_Cached object is returned, pointing to the
 * cached copy of the processed image.
 * @package sapphire
 * @subpackage filesystem
 */
class S3Image_Cached extends S3Image {
	/**
	 * Create a new cached image.
	 * @param string $filename The filename of the image.
	 * @param boolean $isSingleton This this to true if this is a singleton() object, a stub for calling methods.  Singletons
	 * don't have their defaults set.
	 */
	public function __construct($filename = null, $isSingleton = false) {
		parent::__construct(array(), $isSingleton);
		$this->Filename = $filename;
	}
	
	public function getRelativePath() {
		return $this->URL;
	}
	
	// Prevent this from doing anything
	public function requireTable() {
		
	}
	
	/**
	 * Get the URL of the cached S3Image
	 * @return string
	 */
	public function getURL () {
	    return $this->Filename;
	}
	
	public function debug() {
		return "S3Image_Cached object for $this->Filename";
	}
}