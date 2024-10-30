<?php

namespace LazyLoadPreload;

if (!defined('WPINC')) { die; }

/**
 * credits:
 * 
 * inline background image html rewrite are forked & modified from Evgeniy Kozenok codes in
 * WP Lozad - https://wordpress.org/plugins/wp-lozad/
 *
 * preload featured images and add no lazy for featured image are forked & modified from Jackson Lewis codes in
 * How to preload images in WordPress
 * https://dev.to/jacksonlewis/how-to-preload-images-in-wordpress-48di
 *
 * specify image dimensions is forked & modified from Fact Maven codes in
 * https://wordpress.org/plugins/specify-image-dimensions/
 */

class LazyLoadPreload {
		
	const EAGER = 'eager';
		
	const LAZY = 'lazy';
		
	const LAZY_CLASS = 'lazyload';
		
	const LAZY_INLINE_STYLE = '.lazyload{will-change: transform;opacity: 0;transition: opacity 0.0225s ease-in,transform 0.0225s ease-in}
								.lazyloaded,.lazyloading{opacity:1;transition: opacity 0.225s ease-in,transform 0.225s ease-in}
                                img:not([src]){opacity:0!important}
                                :not(img,iframe,video).lazyload{background-image:none!important}';
		
	const LAZY_SCRIPT_AFTER = "document.addEventListener('lazybeforeunveil', function(e){
							  const bg = e.target.getAttribute('data-bg');
							  const poster = e.target.getAttribute('data-poster');

							  if(bg){
								e.target.style.backgroundImage = 'url(' + bg + ')';
								e.target.removeAttribute('data-bg');
							  }

							  if(poster){
								e.target.setAttribute('poster', poster);
								e.target.removeAttribute('data-poster');
							  }
							});"; /* (poster) since 1.0.2 */
	
	public function __construct() {
		
        if( !is_admin() ) {
            
            // use output buffering to ensure these will able to execute to all image tags
			add_action( 'template_redirect', function () {
                ob_start( function ($content) {
	                $content = $this->lpam_add_dimensions_to_imgs($content); /* since 1.0.3 */
                    $content = $this->lpam_remove_lazy_loading_attr_to_imgs_iframes($content);
                    $content = $this->lpam_add_nolazy_for_feat_imgs($content);
                    $content = $this->lpam_add_loading_eager_to_imgs($content);
                    $content = $this->lpam_add_loading_lazy_to_imgs_iframes($content);
                    $content = $this->lpam_lazy_load_img_iframe($content);
					$content = $this->lpam_lazy_load_inline_bg_imgs($content);
                    $content = $this->lpam_lazy_load_css_bg_imgs($content);
                    $content = $this->lpam_lazy_load_videos($content);
                    return $content;
                });
            });

            add_action('wp_head', [$this, 'lpam_preload_featured_images'], 0);
			add_action('wp_enqueue_scripts', [$this, 'lpam_lazy_load_scripts'], PHP_INT_MAX);		
            
		}

	}
	
	private function shouldModifyContent() {
		if (in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
			if (is_cart() || is_checkout() || is_account_page() || is_wc_endpoint_url()) {
				return false; // Exclude WooCommerce pages from modification
			}
		}
		// also exclude this from modification
		return !(is_admin() || is_feed() || is_preview() || is_user_logged_in() || is_404());
	}
	
	private function shouldExcludePage() {
		// Check if the current page URL matches any exclusion criteria
		$excludedPages = apply_filters('lazy_load_excluded_pages', array());

		$currentUrl = $_SERVER['REQUEST_URI'];

		foreach ($excludedPages as $excludedPage) {
			if (strpos($currentUrl, $excludedPage) !== false) {
				return true; // Exclude the page from lazy loading
			}
		}

		return false; // Include the page for lazy loading
	}
	
	private function shouldExcludePageID() {
		// Check if the current page ID matches any exclusion criteria
		$excludedPageIDs = apply_filters('lazy_load_excluded_page_ids', array());

		$currentPageID = get_queried_object_id();

		if (in_array($currentPageID, $excludedPageIDs)) {
			return true; // Exclude the page from lazy loading
		}

		return false; // Include the page for lazy loading
	}
	
	private function perform_search_replace($search, $replace, $content) {
        $search = array_unique($search);
        $replace = array_unique($replace);

        $content = str_replace($search, $replace, $content);

        return $content;
    }
	
	public function lpam_remove_lazy_loading_attr_to_imgs_iframes($content) {
	    
	    // return if it's user logged in and is in admin, feed or is a post preview
        if (!$this->shouldModifyContent()) {
			return $content;
		}
		
		if ($this->shouldExcludePage() || $this->shouldExcludePageID()) {
			return $content;
		}
	    
		$matches = array();
		preg_match_all('/<(img|iframe)[\s\r\n]+.*?>/is', $content, $matches); // Targeting img and iframe tags
        
        // Remove any loading="lazy" attribute first as preparation for our loading_ function
		foreach ($matches[0] as $imgTags) {
			$newImgTags = preg_replace('/(loading=(\'|")?lazy(\'|")?)/i', '', $imgTags);
			$content = str_replace($imgTags, $newImgTags, $content);
		}

		return $content;
	}

	public function lpam_lazy_load_img_iframe($content) {
	    
        if (!$this->shouldModifyContent()) {
			return $content;
		}
		
		if ($this->shouldExcludePage() || $this->shouldExcludePageID()) {
			return $content;
		}
            
        $default_exclude_list = array(
            "no-lazy",
            "skip-lazy",
        );
		
		$lpam_extra_exclude_list = apply_filters( 'lpam_extra_exclude_list',
			array()
		);
            
        $matches = array();
        preg_match_all( '/<(img|iframe)[\s\r\n]+.*?>/is', $content, $matches ); // targeting img and iframe tags
    
        $lazy_class = self::LAZY_CLASS;
    
        $search = array();
        $replace = array();
    
        $i = 0;
        
        foreach ( $matches[0] as $ImgIframeTags ) {
            
			$exclude_lazy = array_merge( $default_exclude_list, $lpam_extra_exclude_list);

            // Loop through the exclude list and continue if there's a match
            foreach ( $exclude_lazy as $exclude ) {
                if ( $exclude && strpos($ImgIframeTags, $exclude) !== false ) {
                    continue 2;
                }
            }
            
            // exclude images with class name "logo"
            if ( preg_match( '/class=["\']([^"\']*)logo([^"\']*)["\']/i', $ImgIframeTags ) ) {
                continue;
            }
            
            // don't replace if the image is a data-uri and gravatars
            if ( ! preg_match( "/src=['\"]data:image|https:\/\/secure\.gravatar\.com\/avatar\//is", $ImgIframeTags ) ) {
    
                // replace the src and add the data-src attribute
                $replaceImgIframeTags = '';
                $replaceImgIframeTags = preg_replace( '/<(img|iframe)(.*?)src=/is', '<$1$2 data-size="auto" data-src=', $ImgIframeTags );
                
                // also replace the srcset (responsive images)
                $replaceImgIframeTags = str_replace( 'srcset', 'data-srcset', $replaceImgIframeTags );
    
                // add the lazy class to the img or iframe element
                if ( preg_match( '/class=["\']/i', $replaceImgIframeTags ) ) {
                    $replaceImgIframeTags = preg_replace( '/class=(["\'](.*?)")/is', 'class="'.esc_html($lazy_class).' $2"', $replaceImgIframeTags );
                } else {
                    $replaceImgIframeTags = preg_replace( '/<(img|iframe)/is', '<$1 class="'.esc_html($lazy_class).'"', $replaceImgIframeTags );
                }
				
				$disable_noscript = apply_filters('disable_noscript', false);
                
                // add <noscript> for js disabled browsers
                if (!$disable_noscript) {
                	$replaceImgIframeTags .= '<noscript>' . $ImgIframeTags . '</noscript>';
				}
    
                array_push( $search, $ImgIframeTags );
                array_push( $replace, $replaceImgIframeTags );
            }
                
        }
    
        $content = $this->perform_search_replace($search, $replace, $content);
    
        return $content;
            
    } // end lpam_lazy_load_img_iframe
	
    
    public function lpam_preload_featured_images() {
    
	    global $post;
		
		/* since 1.0.2 */
		$disable_preload = apply_filters('disable_featured_image_preload', false);
		
		if ($disable_preload) {
			return; // Return early if preloading is disabled
		}
	    
	    if (!$this->shouldModifyContent()) {
			return;
		}
		
		if ($this->shouldExcludePage() || $this->shouldExcludePageID()) {
			return;
		}
	    
	    $image_size = 'full'; // default image sizes
	
	    if ( is_singular( 'product' ) ) {
	        $image_size = 'woocommerce_single'; // woocommerce product image sizes
	
	    } else if ( is_singular( 'post' ) ) {
	        $image_size = 'full'; // single post image sizes
	    }
		
	    $image_size = apply_filters( 'lpam_preload_featured_images_image_size', $image_size, $post );
		
	    /** Get post thumbnail if an attachment ID isn't specified. */
	    $thumbnail_id = null;
        if ( isset( $post ) && $post ) {
            $thumbnail_id = apply_filters( 'lpam_preload_featured_images_id', get_post_thumbnail_id( $post->ID ), $post );
        }
	
	    /** Get the image */
	    $image = wp_get_attachment_image_src( $thumbnail_id, $image_size );
	    $src = '';
	
	    if ( $image ) {
			
	        list( $src, $width, $height ) = $image;
	        $image_meta = wp_get_attachment_metadata( $thumbnail_id );
	
	        if ( is_array( $image_meta ) ) {
	            $size_array = array( absint( $width ), absint( $height ) );
	            $srcset     = wp_calculate_image_srcset( $size_array, $src, $image_meta, $thumbnail_id );
	            $sizes      = wp_calculate_image_sizes( $size_array, $src, $image_meta, $thumbnail_id );    
				
	        }
			
	    } else {
	        return;  /** Early exit if no image is found. */
	    }
	    
	   $preload_tag = '<link rel="preload" as="image" href="%s">'. PHP_EOL;
		
	   printf( $preload_tag, esc_url( $src ) );
	    
	}
    
    public function lpam_add_nolazy_for_feat_imgs($content) {
		
		global $post;
		
		if (!$this->shouldModifyContent()) {
			return $content;
		}
		
		if ($this->shouldExcludePage() || $this->shouldExcludePageID()) {
			return $content;
		}
		
		$image_size = 'full';
	
	    if ( is_singular( 'product' ) ) {
	        $image_size = 'woocommerce_single';
	
	    } else if ( is_singular( 'post' ) ) {
	        $image_size = 'full';
	    }
		
	    $image_size = apply_filters( 'lpam_preload_featured_images_image_size', $image_size, $post );
		
	    /** Get post thumbnail if an attachment ID isn't specified. */
	    $thumbnail_id = apply_filters( 'lpam_preload_featured_images_id', get_post_thumbnail_id( $post->ID ), $post );
	
	    /** Get the image */
	    $image = wp_get_attachment_image_src( $thumbnail_id, $image_size );
	    $src = '';

		if ( !$image ) {
			return $content;
		}
	
	    if ( $image ) {
	        list( $src, $width, $height ) = $image;
	    } else {
	        return;
	    }
		
		$src = str_replace("/", "\/", $src);
		
		$feat_img = basename($src);
		
		$pattern = "/<img[^>]*src=(.*?)(.*$feat_img)(.*?)>/i";
		
		$matches = array();
		preg_match_all( $pattern, $content, $matches );
		
		$search = array();
		$replace = array();
		
		$nolazy = 'no-lazy skip-lazy';

		foreach ( $matches[0] as $imgNotLazy ) {

        	if ( preg_match( '/class=["\']/is', $imgNotLazy ) ) {
				$replaceImgNoLazy = preg_replace( '/class=(["\'])(.*?)["\']/is', 'class="'.esc_html($nolazy).' $2$1', $imgNotLazy );
			} else {
				$replaceImgNoLazy = preg_replace( '/<img/is', '<img class="'.esc_html($nolazy).'"', $imgNotLazy );
			}
			
			array_push( $search, $imgNotLazy );
			array_push( $replace, $replaceImgNoLazy );
    
		}
		
		$content = $this->perform_search_replace($search, $replace, $content);
		
		return $content;
		
	}
	
	public function lpam_add_loading_eager_to_imgs($content) {
		
		if (!$this->shouldModifyContent()) {
			return $content;
		}
		
		if ($this->shouldExcludePage() || $this->shouldExcludePageID()) {
			return $content;
		}
		
		$default_exclude_list = array(
			"no-lazy",
			"skip-lazy",
		);
		
		$lpam_extra_exclude_list = apply_filters( 'lpam_extra_exclude_list',
			array()
		);
		
		$matches = array();
		preg_match_all( '@<img(?:(?!loading=).)*?>@', $content, $matches );

		$eager = self::EAGER;

		$search = array();
		$replace = array();

		$i = 0;
		foreach ( $matches[0] as $imgNotLazy ) {

			$loading_eager = array_merge( $default_exclude_list, $lpam_extra_exclude_list);

			// Check if the image has the class "logo"
			preg_match('/class="[^"]*logo[^"]*"/', $imgNotLazy, $classMatch);
			if (!empty($classMatch[0])) {
				$imgNotLazy = str_replace('<img', '<img loading="' . esc_html($eager) . '" fetchpriority="high"', $imgNotLazy);
				array_push($search, $matches[0][$i]);
				array_push($replace, $imgNotLazy);
			} else {
				foreach ($loading_eager as $replaceImgNoLazy) {
					if ($replaceImgNoLazy && strpos($imgNotLazy, $replaceImgNoLazy) !== false) {
						$replaceImgNoLazy = preg_replace( '/<img/is', '<img loading="'.esc_html($eager).'" fetchpriority="high"', $imgNotLazy );
						array_push( $search, $imgNotLazy );
						array_push( $replace, $replaceImgNoLazy );
					}
				}
			}
			$i++;
		}

		$content = $this->perform_search_replace($search, $replace, $content);

		return $content;

	} // end lpam_add_loading_eager_to_imgs
	
	public function lpam_add_loading_lazy_to_imgs_iframes($content) {
		
		if (!$this->shouldModifyContent()) {
			return $content;
		}
		
		if ($this->shouldExcludePage() || $this->shouldExcludePageID()) {
			return $content;
		}

		$default_exclude_list = array(
			"no-lazy",
			"skip-lazy",
		);

		$lpam_extra_exclude_list = apply_filters('lpam_extra_exclude_list', array());

		// Match both images and iframes
		$matches = array();
		preg_match_all('/<(img|iframe)(?:(?!loading=).)*?>/', $content, $matches);

		$lazy = self::LAZY;

		$search = array();
		$replace = array();

		foreach ($matches[0] as $lazyElement) {
			$loading_lazy = array_merge($default_exclude_list, $lpam_extra_exclude_list);

			foreach ($loading_lazy as $replaceLazyElement) {
				if ($replaceLazyElement && strpos($lazyElement, $replaceLazyElement) !== true) {
					$replaceLazyElement = preg_replace('/<(img|iframe)/is', '<$1 loading="'.esc_html($lazy).'" fetchpriority="low"', $lazyElement);
					array_push($search, $lazyElement);
					array_push($replace, $replaceLazyElement);
				}
			}
		}

		$content = $this->perform_search_replace($search, $replace, $content);

		return $content;
	} // end lpam_add_loading_lazy_to_imgs_iframes
	
	
	// start LazyLoad BG_Images
	public function lpam_lazy_load_inline_bg_imgs($content) {
	    
	    if (!$this->shouldModifyContent()) {
			return $content;
		}
		
		if ($this->shouldExcludePage() || $this->shouldExcludePageID()) {
			return $content;
		}
		
		$default_exclude_list = array(
			"no-lazy",
			"skip-lazy"
		);
		
		$lpam_extra_exclude_list = apply_filters( 'lpam_extra_exclude_list',
			array()
		);
    
        $bg_imgs = ['background-image', 'background'];
            
		$lazy_class = self::LAZY_CLASS;
            
		$search = array();
		$replace = array();

		foreach ($bg_imgs as $bg_img) {
                
			$bg_img_matches = [];
			preg_match_all('/<[^>]*?style=[^>]*?' . $bg_img . '\s*?:\s*?url\s*\([^>]+\)[^>]*?>/', $content, $bg_img_matches);
    
			foreach ($bg_img_matches[0] as $bg_img_tags) {
				
				$exclude_lazy = array_merge( $default_exclude_list, $lpam_extra_exclude_list);
                    
				foreach ($exclude_lazy as $exclude) {
					if ( $exclude && strpos($bg_img_tags, $exclude) !== false ) {
					continue 2;
					}
				}
                    
				if (preg_match("/url=['\"]data:image/is", $bg_img_tags) !== 0) {
					continue;
				}
    
				$bgImgMatches = [];
				preg_match('/' . $bg_img . ':\s*url\s*\(\s*[\'"]?([^\'"]*)[\'"]?\)/im', $bg_img_tags, $bgImgMatches);
    
				$bgImg = isset($bgImgMatches[1]) ? $bgImgMatches[1] : null;
				
				if (empty($bgImg)) {
					continue;
				}
    
				$bgImgSlashes = preg_replace(['/\//', '/\./'], ['\/', '\.'], $bgImg);
    
				// remove bg image from style tag
				$replaceBGimgsHTML = preg_replace('/(.*?style=.*?)' . $bg_img . ':\s*url\s*\(\s*[\'"]' . $bgImgSlashes . '[\'"]\s*\);*\s*(.*?)/is', '$1$2', $bg_img_tags);
				$replaceBGimgsHTML = preg_replace('/(.*?style=.*?)' . $bg_img . ':\s*url\s*\(\s*' . $bgImgSlashes . '\s*\);*\s*(.*?)/is', '$1$2', $replaceBGimgsHTML);

				// add lazyload class
				$replaceBGimgsHTML = preg_replace('/(.*?)class=([\'"])(.*?)/is', '$1class=$2' . esc_html($lazy_class) . ' $3', $replaceBGimgsHTML);
    				
                // add bg img url to data-background-image
				$replaceBGimgsHTML = preg_replace('/<(.*)>/is', '<$1 data-bg="' . $bgImg . '">', $replaceBGimgsHTML);
                    
				array_push( $search, $bg_img_tags );
				array_push( $replace, $replaceBGimgsHTML );
			}
		}
            
		$content = $this->perform_search_replace($search, $replace, $content);
    
		return $content;
            
	} // end lpam_lazy_load_inline_bg_imgs
	
	
	public function lpam_lazy_load_css_bg_imgs($content) {
		
		if (!$this->shouldModifyContent()) {
			return $content;
		}
		
		$lazy_bgimg_class = self::LAZY_CLASS;
		
		$lazy_bg_class = array(
				"lazyload",
				"lazy-bg"
			);
		 
		$lpam_extra_lazy_bg_class = apply_filters( 'lpam_extra_lazy_bg_class',
			array()
		);
		
		$matches = array();
		/*preg_match_all('/<(div|section|footer)[\s\r\n]+.*?<\/\1>/is', $content, $matches);*/
		preg_match_all( '/<(div|section|footer)[\s\r\n]+.*?>/is', $content, $matches );

		$search = array();
		$replace = array();
		
		foreach ( $matches[0] as $divTags ) {
			
			$lazy_bg = array_merge( $lazy_bg_class, $lpam_extra_lazy_bg_class);
		    
		    foreach ($lazy_bg as $replacedivTags) {
				if ($replacedivTags && strpos($divTags, $replacedivTags) !== false) {        			
					$replacedivTags = preg_replace( '/<(' . $matches[1][$i] . ')(.*?)class="/is', '<$1$2class="'.esc_html($lazy_bgimg_class).' ', $divTags );
					array_push( $search, $divTags );
					array_push( $replace, $replacedivTags );
    		    }
		    }
			
		}
		$content = $this->perform_search_replace($search, $replace, $content);
		return $content;
    } // end lpam_lazy_load_css_bg_imgs

	// start lpam_lazy_load_videos
	public function lpam_lazy_load_videos($content) {
            
		if (!$this->shouldModifyContent()) {
			return $content;
		}
		
		if ($this->shouldExcludePage() || $this->shouldExcludePageID()) {
			return $content;
		}
		
		$default_exclude_list = array(
			"no-lazy",
			"skip-lazy"
		);
		
		$lpam_extra_exclude_list = apply_filters( 'lpam_extra_exclude_list',
			array()
		);
			
		$lazy_class = self::LAZY_CLASS;
            
		$search = array();
		$replace = array();
			
		$videoPosterTags = [];
		preg_match_all('/<video[^>]*?>(.*?)<\/video>/sim', $content, $videoPosterTags);
    
		foreach ($videoPosterTags[0] as $videoPosterTag) {
                
			$exclude_lazy = array_merge( $default_exclude_list, $lpam_extra_exclude_list);

			foreach ($exclude_lazy as $exclude) {
				if ( $exclude && strpos($videoPosterTag, $exclude) !== false ) {
					continue 2;
				}
			}
                
			if (preg_match("/src=['\"]data:image/is", $videoPosterTag) !== 0) {
				continue;
			}
    			
			$replacevideoPosterTag = preg_replace( '/<video(.*?)src=/is', '<video$1data-src=', $videoPosterTag );
			$replacevideoPosterTag = preg_replace('/<(.*)poster=/is', '<$1 data-poster=', $replacevideoPosterTag);
    
			if (preg_match('/class=["\']/i', $replacevideoPosterTag)) {
				$replacevideoPosterTag = preg_replace('/class=(["\'])(.*?)["\']/is', "class=$1{$lazy_class} $2$1", $replacevideoPosterTag);
			} else {
				$replacevideoPosterTag = preg_replace('/<video/is', '<video class="' . esc_html($lazy_class) . '"', $replacevideoPosterTag);
			}
    
			array_push( $search, $videoPosterTag );
			array_push( $replace, $replacevideoPosterTag );
			
			$videoTags = [];
			preg_match_all('/<source[^>]*?\/>/sim', $videoPosterTag, $videoTags);
    
			foreach ($videoTags[0] as $videoTag) {
                    
				$exclude_keywords = get_option('opm_img_options')['lazyload_exclude_list'];
				$exclude_keywords = explode("\n", str_replace("\r", "", $exclude_keywords));
                    
                foreach ($exclude_lazy as $exclude) {
					if ( $exclude && strpos($videoTag, $exclude) !== false ) {
						continue 2;
					}
				}
                    
				$replaceVideoTag = preg_replace('/<(.*)src=/is', '<$1 data-src=', $videoTag);

				array_push( $search, $videoTag );
				array_push( $replace, $replaceVideoTag );
					
			}
		}
    
		$content = str_replace( $search, $replace, $content );
    
		return $content;
	
    } // end lpam_lazy_load_videos
    
    /* since 1.0.3 */
	public function lpam_add_dimensions_to_imgs($content) {
		
		if (!$this->shouldModifyContent()) {
			return $content;
		}
		
		if ($this->shouldExcludePage() || $this->shouldExcludePageID()) {
			return $content;
		}
	    
		$disable_img_dimension = apply_filters('disable_img_dimension', false);
		
		if ($disable_img_dimension) {
			return $content; // Return early if preloading is disabled
		}
		
		preg_match_all( '/<img[^>]+>/i', $content, $images ); // Find all image tags
		
		if ( count( $images ) < 1 ) {
		    return $content; // If there are no images, return
		}

		foreach ( $images[0] as $image ) {
		    # Match all image attributes
		    $attributes = 'src|srcset|longdesc|alt|class|id|usemap|align|border|hspace|vspace|crossorigin|ismap|sizes|width|height';
		    preg_match_all( '/(' . $attributes . ')=("[^"]*")/i', $image, $img );
		    # If image has a 'src', continue
		    if ( ! in_array( 'src', $img[1] ) ) {
		        continue;
		    }
		    # If no 'width' or 'height' is available or blank, calculate dimensions
		    if ( ! in_array( 'width', $img[1] ) || ! in_array( 'height', $img[1] ) || ( in_array( 'width', $img[1] ) && in_array( '""', $img[2] ) ) || ( in_array( 'height', $img[1] ) && in_array( '""', $img[2] ) ) ) {
		        # Split up string of attributes into variables
		        $attributes = explode( '|', $attributes );
		        foreach ( $attributes as $variable ) {
					${$variable} = in_array( $variable, $img[1] ) ? ' ' . $variable . '=' . $img[2][array_search( $variable, $img[1] )] : '';
		        }
		        $src = $img[2][array_search( 'src', $img[1] )];
		        # If image is an SVG, continue
		        if ( preg_match( '/(.*).svg/i', $src ) ) {
					if ( ! in_array( 'width', $img[1] ) || ! in_array( 'height', $img[1] ) || ( in_array( 'width', $img[1] ) && in_array( '""', $img[2] ) ) || ( in_array( 'height', $img[1] ) && in_array( '""', $img[2] ) ) ) {
						// Remove any existing width and height attributes
						$image = preg_replace('/\s(width|height)="\d*"/i', '', $image);

						// Calculate width and height using getimagesize
						list( $width, $height ) = getimagesize( str_replace( "\"", "" , $src ) );

						// Recreate the image tag with dimensions set
						$tag = sprintf( '<img src=%s%s%s%s%s%s%s%s%s%s%s%s%s%s width="%s" height="%s">', $src, $srcset, $longdesc, $alt, $class, $id, $usemap, $align, $border, $hspace, $vspace, $crossorigin, $ismap, $sizes, $width, $height );

						$content = str_replace( $image, $tag, $content );
					}
				}
		        # Else, get accurate width and height attributes
		        else {
					list( $width, $height ) = getimagesize( str_replace( "\"", "" , $src ) );
		        }
		        # Recreate the image tag with dimensions set
		        $tag = sprintf( '<img src=%s%s%s%s%s%s%s%s%s%s%s%s%s%s width="%s" height="%s">', $src, $srcset, $longdesc, $alt, $class, $id, $usemap, $align, $border, $hspace, $vspace, $crossorigin, $ismap, $sizes, $width, $height );
		        $content = str_replace( $image, $tag, $content );
		    }
		}
		# Return all image with dimensions
		return $content;
    }
	
	public function lpam_lazy_load_scripts() {
	    
	    if (!$this->shouldModifyContent()) {
			return;
		}
		
		if ($this->shouldExcludePage() || $this->shouldExcludePageID()) {
			return;
		}
	    
	    $lpam_lazysizes_js_before = apply_filters( 'lpam_lazysizes_js_before',
		'window.lazySizesConfig = { expand: 400, threshold: 400, loadMode: 1 };'
		);
			
		$lazysizes_js_after = self::LAZY_SCRIPT_AFTER;
		$lazysizes_js_after = preg_replace('/\s+/', ' ', $lazysizes_js_after);
            
		// enqueue the lazysizes js
		wp_enqueue_script('lazysizes', LPAM_VENDOR_URL . 'js/lazysizes.min.js', [], LPAM_VERSION, true, PHP_INT_MAX);
			
		// set up the config
		wp_add_inline_script('lazysizes', wp_kses_data($lpam_lazysizes_js_before), 'before');
		
		wp_add_inline_script('lazysizes', wp_kses_data($lazysizes_js_after) );
		
		wp_register_style( 'lazysizes', false );
        wp_enqueue_style( 'lazysizes' );
            
		$lazysizes_inline_style = self::LAZY_INLINE_STYLE;
		
		// minify the inline style before inject
		$lazysizes_inline_style = preg_replace(['#("(?:[^"\\\]++|\\\.)*+"|\'(?:[^\'\\\\]++|\\\.)*+\')|\/\*(?!\!)(?>.*?\*\/)|^\s*|\s*$#s','#("(?:[^"\\\]++|\\\.)*+"|\'(?:[^\'\\\\]++|\\\.)*+\'|\/\*(?>.*?\*\/))|\s*+;\s*+(})\s*+|\s*+([*$~^|]?+=|[{};,>~]|\s(?![0-9\.])|!important\b)\s*+|([[(:])\s++|\s++([])])|\s++(:)\s*+(?!(?>[^{}"\']++|"(?:[^"\\\]++|\\\.)*+"|\'(?:[^\'\\\\]++|\\\.)*+\')*+{)|^\s++|\s++\z|(\s)\s+#si','#(?<=[\s:])(0)(cm|em|ex|in|mm|pc|pt|px|vh|vw|%)#si','#(?<=[\s:,\-])0+\.(\d+)#s',],['$1','$1$2$3$4$5$6$7','$1','.$1',], $lazysizes_inline_style);
            
		wp_add_inline_style('lazysizes', esc_attr($lazysizes_inline_style));

    } // end lpam_lazy_load_scripts
	
}

$LazyLoadPreload = new LazyLoadPreload();