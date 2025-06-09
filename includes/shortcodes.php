<?php
// shortcodes.php

function alba_board_render_shortcode($atts) {
    $atts = shortcode_atts(['id' => 0], $atts);
    $board_id = intval($atts['id']);
    if (!$board_id) return '<p>' . esc_html__('Invalid board ID', 'alba-board') . '</p>';

    include_once(ABSPATH . 'wp-admin/includes/plugin.php');
    $addon_active = is_plugin_active('alba-board-frontend-interactions/alba-board-frontend-interactions.php');

    ob_start();

    $lists = get_posts([
        'post_type'   => 'alba_list',
        'numberposts' => -1,
        'orderby'     => 'menu_order',
        'order'       => 'ASC',
        'meta_key'    => 'alba_board_parent',
        'meta_value'  => $board_id
    ]);

    $options = get_option('alba_board_limits');
    $max_cards = isset($options['limit_cards']) ? intval($options['limit_cards']) : 0;

    echo '<div class="alba-board-outerwrap">';
    echo '<div class="alba-board-wrapper">';
    foreach ($lists as $list) {
        echo '<div class="alba-list-column">';
        echo '<h3>' . esc_html($list->post_title) . '</h3>';

        $cards = get_posts([
            'post_type'   => 'alba_card',
            'numberposts' => -1,
            'orderby'     => 'menu_order',
            'order'       => 'ASC',
            'meta_key'    => 'alba_list_parent',
            'meta_value'  => $list->ID
        ]);

        echo '<div class="alba-cards" data-list-id="' . esc_attr($list->ID) . '"'
            . ($max_cards > 0 ? ' data-max-cards="' . esc_attr($max_cards) . '"' : '')
            . '>';

        foreach ($cards as $card) {
            echo '<div class="alba-card" data-card-id="' . esc_attr($card->ID) . '">';
            echo '<span class="alba-card-title">' . esc_html($card->post_title) . '</span>';

            $terms = get_the_terms($card->ID, 'alba_tag');
            if (!empty($terms) && !is_wp_error($terms)) {
                echo '<div class="alba-card-tags">';
                foreach ($terms as $term) {
                    $bg   = get_term_meta($term->term_id, 'alba_tag_bg_color', true);
                    $text = get_term_meta($term->term_id, 'alba_tag_text_color', true);
                    $style = '';
                    if ($bg)   $style .= 'background:' . esc_attr($bg) . ';';
                    if ($text) $style .= 'color:' . esc_attr($text) . ';';
                    echo '<span class="alba-card-tag-chip"' . ($style ? ' style="' . esc_attr($style) . '"' : '') . '>' . esc_html($term->name) . '</span>';
                }
                echo '</div>';
            }
            echo '</div>'; // .alba-card
        }

        echo '</div>'; // .alba-cards
        echo '</div>'; // .alba-list-column
    }
    echo '</div>'; // .alba-board-wrapper
    echo '</div>'; // .alba-board-outerwrap

    ?>
    <style>
    #alba-card-modal {
        display: none;
        position: fixed;
        z-index: 99999;
        left: 0; top: 0; width: 100vw; height: 100vh;
        background: rgba(50,54,60,0.18);
        justify-content: center;
        align-items: center;
        transition: opacity .14s;
    }
    #alba-card-modal.active { display: flex !important; }
    .alba-modal-content {
        background: #fff;
        border-radius: 28px;
        box-shadow: 0 0 48px 12px #e0e3ec, 0 -6px 18px 7px #ffffff;
        padding: 2.7rem 2.5rem 2.1rem 2.5rem;
        max-width: 650px;
        width: 98%;
        min-width: 260px;
        max-height: 92vh;
        overflow-y: auto;
        position: relative;
        margin: 0 auto;
        display: flex;
        flex-direction: column;
        align-items: stretch;
        font-family: inherit;
        animation: alba-pop-in .18s;
    }
    @keyframes alba-pop-in { 0% { transform: scale(.93); opacity: 0; } 100% { transform: scale(1); opacity: 1; } }
    #alba-modal-close {
        position: absolute;
        top: 18px; right: 18px;
        background: #f8fafc;
        border: none;
        border-radius: 50%;
        width: 32px; height: 32px;
        box-shadow: 0 2px 6px #e8ebf0;
        color: #666;
        font-size: 1.21em;
        cursor: pointer;
        z-index: 10;
        display: flex;
        align-items: center; justify-content: center;
        transition: box-shadow .16s;
    }
    #alba-modal-close:hover {
        background: #f1f3fa;
        color: #222;
        box-shadow: 0 6px 16px #e0e3ec;
    }
    .alba-modal-delete-btn-wrapper {
        width: 100%;
        display: flex;
        justify-content: flex-start;
        align-items: flex-end;
        margin-top: 18px;
        min-height: 40px;
    }
    .alba-modal-delete-btn {
        background: none;
        border: none;
        cursor: pointer;
        font-size: 2em;
        color: #8f9aad;
        opacity: .85;
        transition: color .13s, opacity .13s;
        margin-left: 6px;
        margin-bottom: 0px;
        padding: 0;
    }
    .alba-modal-delete-btn:hover { color: #c14444; opacity: 1; }
    #alba-comments-list {
        max-height: 190px;
        overflow-y: auto;
        margin-bottom: 12px;
        margin-top: 4px;
    }
    .alba-board-comment {
        margin-bottom: 8px;
        padding: 9px 10px;
        border-radius: 13px;
        background: #f5f7fa;
        border: 1.5px solid #e0e0e0;
        box-shadow: 1.7px 1.7px 7px #e3e7ef, -1.5px -1.5px 6px #fff;
        font-size: 1.08em;
        font-family: inherit;
        color: #2e3647;
    }
    #alba-new-comment-text {
        background: #f7f9fc;
        border-radius: 14px;
        border: none;
        box-shadow: 2.2px 2.2px 7px #e3e7ef, -1.5px -1.5px 6px #fff;
        padding: 10px 13px;
        font-size: 1em;
        margin-bottom: 10px;
        resize: vertical;
        outline: none;
        transition: box-shadow 0.17s, background 0.16s;
    }
    #alba-new-comment-text:focus {
        background: #f8fafc;
        box-shadow: 0 4px 18px 3px #d8dee7, 0 -2px 7px 1px #fff;
    }
    #alba-add-comment-btn {
        background: #eaf0fb;
        color: #3d4b5c;
        border: none;
        border-radius: 18px;
        padding: 6px 22px;
        font-size: 1em;
        font-weight: 500;
        margin-top: 2px;
        margin-bottom: 12px;
        box-shadow: 1.2px 2.2px 7px #dde3ec, -1px -1.5px 4px #fff;
        transition: box-shadow 0.13s, background 0.14s;
        cursor: pointer;
    }
    #alba-add-comment-btn:hover,
    #alba-add-comment-btn:focus {
        background: #f6fafd;
        box-shadow: 0 3px 13px #d2d8e1, 0 -1px 6px #fff;
    }
    #alba-comment-feedback {
        margin-top: 5px;
        font-weight: 600;
    }
    </style>
    <?php

    // Modal structure
    echo '<div id="alba-card-modal">';
    echo '<div class="alba-modal-content">';
    echo '<button id="alba-modal-close" title="Close">&times;</button>';
    echo '<div id="alba-modal-body">' . esc_html__('Loading...', 'alba-board') . '</div>';
    echo '</div></div>';

    $js_i18n = [
        'loading'        => esc_html__('Loading...', 'alba-board'),
        'confirm_delete' => esc_html__('Are you sure you want to delete this card?', 'alba-board'),
        'delete_error'   => esc_html__('Error deleting card', 'alba-board'),
    ];
    echo '<script>window.AlbaBoardI18n = ' . json_encode($js_i18n) . ';</script>';

    ?>
    <script>
    document.addEventListener("DOMContentLoaded", () => {
        const modal = document.getElementById("alba-card-modal");
        const modalBody = document.getElementById("alba-modal-body");
        const closeBtn = document.getElementById("alba-modal-close");

        // Show modal when clicking a card
        document.querySelectorAll(".alba-card").forEach(card => {
            card.addEventListener("click", () => {
                const cardId = card.dataset.cardId;
                modal.classList.add("active");
                modal.style.display = "flex";
                modalBody.innerHTML = AlbaBoardI18n.loading;
                document.body.style.overflow = "hidden";

                fetch(albaBoard.ajaxurl + "?action=alba_get_card_details&card_id=" + cardId + "&nonce=" + albaBoard.get_card_details_nonce)
                  .then(res => res.text())
                  .then(html => {
                    modalBody.innerHTML = html;

                    <?php if ($addon_active): ?>
                    let existingTrash = document.getElementById("alba-modal-delete");
                    if (existingTrash) existingTrash.parentNode.removeChild(existingTrash);

                    let wrapper = document.createElement("div");
                    wrapper.className = "alba-modal-delete-btn-wrapper";
                    let trashBtn = document.createElement("button");
                    trashBtn.id = "alba-modal-delete";
                    trashBtn.className = "alba-modal-delete-btn";
                    trashBtn.title = "Delete";
                    trashBtn.innerHTML = "&#128465;";
                    wrapper.appendChild(trashBtn);
                    modalBody.appendChild(wrapper);
                    <?php endif; ?>

                    addCommentHandler(cardId);
                    addDeleteHandler(cardId);
                });
            });
        });

        // Close modal
        closeBtn.addEventListener("click", () => {
            modal.classList.remove("active");
            modal.style.display = "none";
            document.body.style.overflow = "";
        });
        window.addEventListener("click", (e) => {
            if (e.target === modal) {
                modal.classList.remove("active");
                modal.style.display = "none";
                document.body.style.overflow = "";
            }
        });

        function addDeleteHandler(cardId) {
            const deleteBtn = document.getElementById("alba-modal-delete");
            if (!deleteBtn) return;
            deleteBtn.onclick = function() {
                if (!confirm(AlbaBoardI18n.confirm_delete)) return;
                const data = new URLSearchParams();
                data.append("action", "alba_delete_card");
                data.append("card_id", cardId);
                data.append("nonce", albaBoardFrontend.nonce);
                fetch(albaBoardFrontend.ajaxurl, {
                  method: "POST",
                  headers: { "Content-Type": "application/x-www-form-urlencoded" },
                  body: data
                })
                .then(res => res.json())
                .then(response => {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert(response.data?.message || response.message || AlbaBoardI18n.delete_error);
                    }
                });
            };
        }

        function addCommentHandler(cardId) {
            const addBtn = document.getElementById("alba-add-comment-btn");
            const textarea = document.getElementById("alba-new-comment-text");
            let feedback = document.getElementById("alba-comment-feedback");
            if (!feedback) {
                feedback = document.createElement("div");
                feedback.id = "alba-comment-feedback";
                addBtn.parentNode.insertBefore(feedback, addBtn.nextSibling);
            }
            if (addBtn && textarea) {
                addBtn.onclick = function() {
                    const comment = textarea.value.trim();
                    if (!comment) return;
                    feedback.textContent = "";
                    addBtn.disabled = true;
                    fetch(albaBoardFrontend.ajaxurl, {
                        method: "POST",
                        headers: { "Content-Type": "application/x-www-form-urlencoded" },
                        body: new URLSearchParams({
                            action: "alba_add_comment",
                            nonce: albaBoardFrontend.nonce,
                            card_id: cardId,
                            comment: comment
                        })
                    })
                    .then(res => res.json())
                    .then(response => {
                        addBtn.disabled = false;
                        if (response.success) {
                            feedback.textContent = response.message || "Comment added!";
                            feedback.style.color = "#17A900";
                            textarea.value = "";
                            setTimeout(() => { feedback.textContent = ""; }, 1800);
                            fetch(albaBoard.ajaxurl + "?action=alba_get_card_details&card_id=" + cardId + "&nonce=" + albaBoard.get_card_details_nonce)
                              .then(res => res.text())
                              .then(html => { 
                                  modalBody.innerHTML = html;
                                  addCommentHandler(cardId);
                                  addDeleteHandler(cardId);
                              });
                        } else {
                            feedback.textContent = (response.data && response.data.message) ? response.data.message : "Failed to add comment.";
                            feedback.style.color = "red";
                        }
                    });
                }
            }
        }
    });
    </script>
    <?php

    return ob_get_clean();
}
add_shortcode('alba_board', 'alba_board_render_shortcode');